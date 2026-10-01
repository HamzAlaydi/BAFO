<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Auth\CurrentActor;
use App\Support\Clock\DbClock;
use App\Support\Clock\PostgresDbClock;
use App\Support\Database\MorphMap;
use App\Support\Files\FileAccessRegistry;
use App\Support\Files\FileStorage;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Pdf\MpdfPdfRenderer;
use App\Support\Pdf\PdfRenderer;
use App\Support\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Application-wide defaults shared by every module, the shared kernel (ARCHITECTURE §4) and
 * module discovery.
 */
final class AppServiceProvider extends ServiceProvider
{
    /**
     * Binds the kernel, then registers every app/Modules/<Module>/<Module>ServiceProvider.php,
     * so adding a module never touches bootstrap/providers.php. The kernel is bound first:
     * module providers use Settings, FileAccessRegistry and the morph map in their own
     * register()/boot().
     */
    public function register(): void
    {
        $this->registerKernel();

        foreach (glob(app_path('Modules/*/*ServiceProvider.php')) ?: [] as $file) {
            $module = basename(dirname($file));
            $class = 'App\\Modules\\'.$module.'\\'.basename($file, '.php');

            if (is_subclass_of($class, ModuleServiceProvider::class)) {
                $this->app->register($class);
            }
        }
    }

    public function boot(): void
    {
        $isProduction = $this->app->isProduction();

        // Dates are immutable everywhere: reassign, never mutate (`$c->ends_at = $c->ends_at->addMinutes(2)`).
        Date::use(CarbonImmutable::class);

        Model::preventLazyLoading(! $isProduction);
        Model::preventSilentlyDiscardingAttributes(! $isProduction);
        DB::prohibitDestructiveCommands($isProduction);

        $this->configureRateLimiting();
    }

    /**
     * The shared kernel of ARCHITECTURE §4 (app/Support).
     */
    private function registerKernel(): void
    {
        // §4.1: reset between requests and queued jobs.
        $this->app->scoped(CurrentActor::class);

        // §4.2: the database clock (tests rebind CarbonDbClock in tests/TestCase.php).
        $this->app->singleton(DbClock::class, PostgresDbClock::class);

        // §4.6 and §4.7: registries that module providers fill in boot().
        $this->app->singleton(Settings::class);
        $this->app->singleton(FileAccessRegistry::class);
        $this->app->singleton(FileStorage::class);

        // §4.11 / §15.1: PDF driver selected by bafo.platform.pdf.driver.
        $this->app->singleton(PdfRenderer::class, static function (Application $app): PdfRenderer {
            $driver = config('bafo.platform.pdf.driver', 'mpdf');

            return match ($driver) {
                'mpdf' => $app->make(MpdfPdfRenderer::class),
                default => throw new InvalidArgumentException('Unsupported PDF driver ['.(is_scalar($driver) ? $driver : '?').'].'),
            };
        });

        // §4.9: aliases only, never class names, in every morph column.
        MorphMap::register();
    }

    /**
     * The shared limiters (ARCHITECTURE §2.2 item 7). Module-specific limiters (`offers`,
     * `public-api`, `oauth-token`) are defined by their modules.
     */
    private function configureRateLimiting(): void
    {
        // All of app v1: per user, per IP for guest endpoints.
        RateLimiter::for('app', static fn (Request $request): Limit => Limit::perMinute(300)
            ->by('user:'.($request->user()?->getAuthIdentifier() ?? 'ip:'.$request->ip())));

        // Login, register, OTP, password, invitation lookup and claim: per IP and per e-mail.
        RateLimiter::for('auth', static function (Request $request): array {
            $limits = [Limit::perMinute(10)->by('ip:'.$request->ip())];
            $email = $request->input('email');

            if (is_string($email) && trim($email) !== '') {
                $limits[] = Limit::perMinute(5)->by('email:'.mb_strtolower(trim($email)));
            }

            return $limits;
        });

        // Guest forms (contact).
        RateLimiter::for('guest-forms', static fn (Request $request): Limit => Limit::perHour(5)
            ->by('ip:'.$request->ip()));

        // Guest actions (invitation decline by token).
        RateLimiter::for('guest-actions', static fn (Request $request): Limit => Limit::perMinute(10)
            ->by('ip:'.$request->ip()));
    }
}
