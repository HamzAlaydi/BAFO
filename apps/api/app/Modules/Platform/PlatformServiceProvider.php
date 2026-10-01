<?php

declare(strict_types=1);

namespace App\Modules\Platform;

use App\Modules\Platform\Console\Commands\PrunePlatformCommand;
use App\Modules\Platform\Policies\FilePolicy;
use App\Support\Files\File;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Settings\Settings;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Platform module (owner of the shared kernel in app/Support and the shared files).
 *
 * Tables files, audit_logs, app_settings, idempotency_keys, contact_messages and
 * legal_documents. Endpoints: app config, server time, health, contact, legal pages and
 * private file download. The kernel services themselves (Settings, FileStorage,
 * FileAccessRegistry, DbClock, PdfRenderer, CurrentActor, morph map) are bound in
 * App\Providers\AppServiceProvider before any module provider registers.
 *
 * HTTP routes: routes/app_v1/platform.php.
 * Conventions (config, migrations, views, web routes): App\Support\Modules\ModuleServiceProvider.
 */
final class PlatformServiceProvider extends ModuleServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        File::class => FilePolicy::class,
    ];

    public function boot(): void
    {
        parent::boot();

        /** @var array<string, mixed> $defaults */
        $defaults = config('bafo.platform.settings', []);
        $this->app->make(Settings::class)->defaults($defaults);

        if ($this->app->runningInConsole()) {
            $this->commands([PrunePlatformCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('platform:prune')->daily()->onOneServer();
            $schedule->command('horizon:snapshot')->everyFiveMinutes();
        });
    }
}
