<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Base provider for app/Modules/<Module>/<Module>ServiceProvider.php.
 *
 * Module providers are discovered automatically (AppServiceProvider::register()).
 * By convention this base wires, when present in the module directory:
 *
 *   config.php            merged into config('bafo.<module_snake>')
 *   Database/Migrations/  loadMigrationsFrom()
 *   Resources/views/      loadViewsFrom(..., '<module_snake>')   e.g. billing::pdf.invoice
 *   Routes/web.php        non-API web routes, middleware "web"
 *   $policies             Gate::policy() for each model => policy
 *
 * Subclasses that override register() or boot() must call the parent method.
 * See docs/build/ARCHITECTURE.md §3.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected array $policies = [];

    public function register(): void
    {
        $config = $this->modulePath('config.php');

        if (is_file($config)) {
            $this->mergeConfigFrom($config, 'bafo.'.$this->moduleSnake());
        }
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        if (is_dir($migrations = $this->modulePath('Database/Migrations'))) {
            $this->loadMigrationsFrom($migrations);
        }

        if (is_dir($views = $this->modulePath('Resources/views'))) {
            $this->loadViewsFrom($views, $this->moduleSnake());
        }

        if (is_file($webRoutes = $this->modulePath('Routes/web.php')) && ! $this->app->routesAreCached()) {
            Route::middleware('web')->group($webRoutes);
        }
    }

    protected function modulePath(string $path = ''): string
    {
        $directory = dirname((string) (new ReflectionClass(static::class))->getFileName());

        return $path === '' ? $directory : $directory.DIRECTORY_SEPARATOR.$path;
    }

    protected function moduleSnake(): string
    {
        return Str::snake(basename($this->modulePath()));
    }
}
