<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Pages\ManageSettings;
use App\Modules\Integrations\Http\Middleware\AuthenticatePublicApiClient;
use App\Modules\Platform\Actions\UpdateAppSetting;
use App\Support\Audit\AuditLog;
use App\Support\Auth\Actor;
use App\Support\Auth\ActorType;
use App\Support\Features\Feature;
use App\Support\Features\FeatureFlags;
use App\Support\Features\ReleaseScope;
use App\Support\Http\ApiResponse;
use App\Support\Http\Middleware\EnsureFeatureEnabled;
use App\Support\Settings\Settings;
use Illuminate\Cache\CacheManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Admin\AdminPanel;
use Tests\Support\Identity\Accounts;

/*
|--------------------------------------------------------------------------
| Release scope (docs/build/RELEASE_SCOPE.md §1)
|--------------------------------------------------------------------------
|
| The setting `platform.release_scope`, the derived flags in GET /app-config, the `feature:`
| route middleware, the routes it gates (§1.5), the read-only exceptions for existing records,
| the artisan command and the admin setting. tests/TestCase.php runs the suite as `full`; these
| tests switch to `core` explicitly with $this->releaseScope(ReleaseScope::Core).
|
*/

/**
 * The binding values of §1.3, in catalogue order: flag => [core, full].
 *
 * @return array<string, array{0: bool, 1: bool}>
 */
function releaseScopeCatalogue(): array
{
    return [
        'team_management' => [false, true],
        'vendor_directory' => [false, true],
        'integrations_api' => [false, true],
        'csv_import_export' => [false, true],
        'sponsorship' => [false, true],
        'bafo_round' => [false, true],
        'sealed_format' => [false, true],
        'advanced_rules' => [false, true],
        'final_pricing_window' => [false, true],
        'deletion_approval' => [false, false],
        'deleted_competitions' => [false, false],
        'offer_report' => [false, false],
        'login_as' => [false, false],
        'google_signin' => [false, false],
        'dark_mode' => [false, true],
        'billing_invoices' => [false, true],
        'custom_plan_quote' => [false, true],
        'coupons' => [false, true],
        'qa_comments' => [true, true],
        'attachments' => [true, true],
        'extend_competition' => [false, true],
        'cancel_competition' => [true, true],
    ];
}

function releaseScopeAdminActor(int $id = 7): Actor
{
    $admin = new class extends Model {};
    $admin->forceFill(['id' => $id, 'name' => 'Ops Admin']);

    return Actor::forAdmin($admin);
}

/**
 * @return array<string, bool>
 */
function releaseScopeFlags(ReleaseScope $scope): array
{
    return array_map(static fn (array $values): bool => $values[$scope->isFull() ? 1 : 0], releaseScopeCatalogue());
}

/**
 * The app v1 routes of §1.5 that answer 404 `feature_disabled` while their flag is off: route name => flag.
 *
 * @return array<string, string>
 */
function releaseScopeGatedRoutes(): array
{
    return [
        'app.v1.team.members.index' => 'team_management',
        'app.v1.team.members.store' => 'team_management',
        'app.v1.team.members.update' => 'team_management',
        'app.v1.team.members.destroy' => 'team_management',
        'app.v1.team.members.resend' => 'team_management',
        'app.v1.vendors.index' => 'vendor_directory',
        'app.v1.vendors.store' => 'vendor_directory',
        'app.v1.vendors.show' => 'vendor_directory',
        'app.v1.vendors.update' => 'vendor_directory',
        'app.v1.vendors.destroy' => 'vendor_directory',
        'app.v1.integrations.api-clients.index' => 'integrations_api',
        'app.v1.integrations.api-clients.store' => 'integrations_api',
        'app.v1.integrations.api-clients.show' => 'integrations_api',
        'app.v1.integrations.api-clients.update' => 'integrations_api',
        'app.v1.integrations.api-clients.destroy' => 'integrations_api',
        'app.v1.integrations.api-clients.rotate-secret' => 'integrations_api',
        'app.v1.integrations.api-clients.keys.store' => 'integrations_api',
        'app.v1.integrations.api-clients.keys.destroy' => 'integrations_api',
        'app.v1.integrations.webhook-event-types' => 'integrations_api',
        'app.v1.integrations.webhook-endpoints.index' => 'integrations_api',
        'app.v1.integrations.webhook-endpoints.store' => 'integrations_api',
        'app.v1.integrations.webhook-endpoints.show' => 'integrations_api',
        'app.v1.integrations.webhook-endpoints.update' => 'integrations_api',
        'app.v1.integrations.webhook-endpoints.destroy' => 'integrations_api',
        'app.v1.integrations.webhook-endpoints.test' => 'integrations_api',
        'app.v1.integrations.webhook-endpoints.rotate-secret' => 'integrations_api',
        'app.v1.integrations.webhook-endpoints.deliveries' => 'integrations_api',
        'app.v1.integrations.webhook-deliveries.redeliver' => 'integrations_api',
        'app.v1.integrations.imports.template' => 'csv_import_export',
        'app.v1.integrations.imports.store' => 'csv_import_export',
        'app.v1.integrations.imports.show' => 'csv_import_export',
        'app.v1.integrations.exports.store' => 'csv_import_export',
        'app.v1.integrations.exports.show' => 'csv_import_export',
        'app.v1.competitions.sponsorship.checkout' => 'sponsorship',
        'app.v1.competitions.bafo-round.store' => 'bafo_round',
        'app.v1.competitions.extend' => 'extend_competition',
        'app.v1.plans.custom-quote' => 'custom_plan_quote',
        'app.v1.billing.coupons.validate' => 'coupons',
        // Core in both scopes; the gate stays attached so a later release can flip them.
        'app.v1.competitions.comments.index' => 'qa_comments',
        'app.v1.competitions.comments.store' => 'qa_comments',
        'app.v1.competitions.attachments.index' => 'attachments',
        'app.v1.competitions.attachments.store' => 'attachments',
        'app.v1.competitions.attachments.update' => 'attachments',
        'app.v1.competitions.attachments.destroy' => 'attachments',
        'app.v1.competitions.cancel' => 'cancel_competition',
    ];
}

describe('the setting', function () {
    it('defaults to core in the configuration while the test suite runs as full', function () {
        expect(config('bafo.platform.settings')['platform.release_scope'])->toBe('core')
            ->and(FeatureFlags::SETTING_KEY)->toBe('platform.release_scope')
            ->and(app(FeatureFlags::class)->scope())->toBe(ReleaseScope::Full);

        // A Settings instance that knows the module defaults only (no tests/TestCase.php override).
        $settings = new Settings(app(CacheManager::class));
        $settings->defaults(config('bafo.platform.settings'));

        expect((new FeatureFlags($settings))->scope())->toBe(ReleaseScope::Core);
    });

    it('switches with the stored value and reads anything unknown as core', function () {
        $this->releaseScope(ReleaseScope::Core);
        expect(app(FeatureFlags::class)->scope())->toBe(ReleaseScope::Core);

        $this->releaseScope(ReleaseScope::Full);
        expect(app(FeatureFlags::class)->scope())->toBe(ReleaseScope::Full);

        app(Settings::class)->set(FeatureFlags::SETTING_KEY, 'everything', null);
        expect(app(FeatureFlags::class)->scope())->toBe(ReleaseScope::Core)
            ->and(ReleaseScope::fromSetting(null))->toBe(ReleaseScope::Core)
            ->and(ReleaseScope::values())->toBe(['core', 'full']);
    });

    it('accepts core and full only through UpdateAppSetting', function () {
        $actor = releaseScopeAdminActor();

        app(UpdateAppSetting::class)->handle(FeatureFlags::SETTING_KEY, 'core', $actor);
        expect(app(FeatureFlags::class)->scope())->toBe(ReleaseScope::Core);

        expect(fn () => app(UpdateAppSetting::class)->handle(FeatureFlags::SETTING_KEY, 'everything', $actor))
            ->toThrow(InvalidArgumentException::class, 'core, full');
        expect(fn () => app(UpdateAppSetting::class)->handle(FeatureFlags::SETTING_KEY, true, $actor))
            ->toThrow(InvalidArgumentException::class);

        expect(app(FeatureFlags::class)->scope())->toBe(ReleaseScope::Core)
            ->and(AuditLog::query()->where('action', 'setting.updated')->count())->toBe(1);
    });

    it('labels both scopes in Arabic and English', function () {
        expect(ReleaseScope::Core->label('ar'))->toBe('أساسي (المناقصات والمزايدات)')
            ->and(ReleaseScope::Full->label('en'))->toBe('Full (every feature)');
    });
});

describe('the flags', function () {
    it('has exactly the 22 flags of §1.3 in catalogue order', function () {
        expect(Feature::values())->toBe(array_keys(releaseScopeCatalogue()))
            ->and(Feature::cases())->toHaveCount(22);
    });

    it('derives every flag from the scope', function (string $flag, bool $core, bool $full) {
        $this->releaseScope(ReleaseScope::Core);
        expect(app(FeatureFlags::class)->enabled($flag))->toBe($core);

        $this->releaseScope(ReleaseScope::Full);
        expect(app(FeatureFlags::class)->enabled($flag))->toBe($full)
            ->and(app(FeatureFlags::class)->enabled(Feature::from($flag)))->toBe($full);
    })->with(array_map(static fn (string $flag, array $values): array => [$flag, ...$values], array_keys(releaseScopeCatalogue()), releaseScopeCatalogue()));

    it('lists all flags in catalogue order', function (ReleaseScope $scope) {
        $this->releaseScope($scope);

        expect(app(FeatureFlags::class)->all())->toBe(releaseScopeFlags($scope));
    })->with(['core' => [ReleaseScope::Core], 'full' => [ReleaseScope::Full]]);

    it('refuses a flag name outside the catalogue', function () {
        app(FeatureFlags::class)->enabled('sealed_bids');
    })->throws(InvalidArgumentException::class, 'Unknown feature flag [sealed_bids]');

    it('keeps sponsorship behind the Billing switch as well as the scope', function () {
        $settings = app(Settings::class);

        $this->releaseScope(ReleaseScope::Full);
        expect(app(FeatureFlags::class)->enabled(Feature::Sponsorship))->toBeTrue();

        $settings->set('sponsorship.enabled', false, null);
        expect(app(FeatureFlags::class)->enabled(Feature::Sponsorship))->toBeFalse();

        $settings->set('sponsorship.enabled', true, null);
        $this->releaseScope(ReleaseScope::Core);
        expect(app(FeatureFlags::class)->enabled(Feature::Sponsorship))->toBeFalse();
    });
});

describe('GET /app-config', function () {
    it('exposes the scope and the 22 flags with the §1.4 values', function (ReleaseScope $scope) {
        $this->releaseScope($scope);

        $response = $this->getJson('/api/app/v1/app-config')
            ->assertOk()
            ->assertJsonPath('data.features.release_scope', $scope->value)
            ->assertJsonPath('data.features.flags', releaseScopeFlags($scope))
            ->assertJsonPath('data.features.sponsorship', $scope->isFull());

        expect(array_keys($response->json('data.features.flags')))->toBe(Feature::values())
            ->and(array_keys($response->json('data.features')))->toBe(['release_scope', 'sponsorship', 'flags']);
    })->with(['core' => [ReleaseScope::Core], 'full' => [ReleaseScope::Full]]);

    it('keeps features.sponsorship equal to flags.sponsorship', function () {
        app(Settings::class)->set('sponsorship.enabled', false, null);

        $this->getJson('/api/app/v1/app-config')
            ->assertJsonPath('data.features.release_scope', 'full')
            ->assertJsonPath('data.features.sponsorship', false)
            ->assertJsonPath('data.features.flags.sponsorship', false)
            ->assertJsonPath('data.features.flags.bafo_round', true);
    });
});

describe('the feature middleware', function () {
    beforeEach(function () {
        Route::prefix('api/app/v1/__scope')->middleware('app_v1')->withoutMiddleware('auth:sanctum')->group(function () {
            Route::get('gated', fn () => ApiResponse::ok(['ok' => true]))->middleware('feature:bafo_round');
            Route::get('always', fn () => ApiResponse::ok(['ok' => true]))->middleware('feature:qa_comments');
            Route::get('reserved', fn () => ApiResponse::ok(['ok' => true]))->middleware('feature:login_as');
        });
    });

    it('is registered as the feature alias and ranks after authentication, before throttling, binding and authorization', function () {
        $router = app(Router::class);
        $priority = $router->middlewarePriority;

        expect($router->getMiddleware()['feature'] ?? null)->toBe(EnsureFeatureEnabled::class)
            ->and(array_search(EnsureFeatureEnabled::class, $priority, true))
            ->toBeGreaterThan(array_search('Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests', $priority, true))
            ->toBeLessThan(array_search(ThrottleRequests::class, $priority, true))
            ->toBeLessThan(array_search(SubstituteBindings::class, $priority, true));

        $position = static function (string $routeName, string $needle): int {
            $route = app(Router::class)->getRoutes()->getByName($routeName);
            $sorted = app(Router::class)->gatherRouteMiddleware($route);

            foreach ($sorted as $index => $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, $needle)) {
                    return $index;
                }
            }

            return -1;
        };

        // App v1: a bogus id or a missing permission never hides the 404 feature_disabled.
        expect($position('app.v1.competitions.bafo-round.store', 'Illuminate\Auth\Middleware\Authenticate'))
            ->toBeLessThan($position('app.v1.competitions.bafo-round.store', EnsureFeatureEnabled::class))
            ->and($position('app.v1.competitions.bafo-round.store', EnsureFeatureEnabled::class))
            ->toBeLessThan($position('app.v1.competitions.bafo-round.store', ThrottleRequests::class))
            ->toBeLessThan($position('app.v1.competitions.bafo-round.store', SubstituteBindings::class))
            ->and($position('app.v1.team.members.index', EnsureFeatureEnabled::class))
            ->toBeLessThan($position('app.v1.team.members.index', 'Illuminate\Auth\Middleware\Authorize'));

        // Public v1: the gate precedes the client authentication, which still precedes its throttle.
        expect($position('public.v1.vendors.index', EnsureFeatureEnabled::class))
            ->toBeLessThan($position('public.v1.vendors.index', AuthenticatePublicApiClient::class))
            ->and($position('public.v1.vendors.index', AuthenticatePublicApiClient::class))
            ->toBeLessThan($position('public.v1.vendors.index', ThrottleRequests::class));
    });

    it('answers 404 feature_disabled with the flag in details, in Arabic and English', function () {
        $this->releaseScope(ReleaseScope::Core);

        $this->getJson('/api/app/v1/__scope/gated')
            ->assertNotFound()
            ->assertHeader('Content-Language', 'ar')
            ->assertExactJson([
                'message' => 'هذه الميزة غير متاحة في هذا الإصدار.',
                'code' => 'feature_disabled',
                'errors' => [],
                'details' => ['feature' => 'bafo_round'],
            ]);

        $this->getJson('/api/app/v1/__scope/gated', ['Accept-Language' => 'en'])
            ->assertNotFound()
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('message', 'This feature is not available in this release.')
            ->assertJsonPath('details.feature', 'bafo_round');

        expect(__('errors.feature_disabled_field', [], 'ar'))->toBe('هذا الخيار غير متاح في هذا الإصدار.')
            ->and(__('errors.feature_disabled_field', [], 'en'))->toBe('This option is not available in this release.');
    });

    it('lets the route through once the scope enables the flag', function () {
        $this->releaseScope(ReleaseScope::Core);
        $this->getJson('/api/app/v1/__scope/gated')->assertNotFound();

        $this->releaseScope(ReleaseScope::Full);
        $this->getJson('/api/app/v1/__scope/gated')->assertOk()->assertJsonPath('data.ok', true);
    });

    it('passes core flags in both scopes and blocks reserved flags in both', function (ReleaseScope $scope) {
        $this->releaseScope($scope);

        $this->getJson('/api/app/v1/__scope/always')->assertOk();
        $this->getJson('/api/app/v1/__scope/reserved')->assertNotFound()->assertJsonPath('details.feature', 'login_as');
    })->with(['core' => [ReleaseScope::Core], 'full' => [ReleaseScope::Full]]);

    it('treats an unknown flag name as a programming error', function () {
        app(EnsureFeatureEnabled::class)->handle(Request::create('/x'), static fn (): Response => new Response, 'sealed_bids');
    })->throws(InvalidArgumentException::class);
});

describe('gated routes (§1.5)', function () {
    it('attaches the flag to every hidden app v1 route', function (string $routeName, string $flag) {
        $route = app(Router::class)->getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull()
            ->and($route?->middleware())->toContain('feature:'.$flag);
    })->with(array_map(
        static fn (string $routeName, string $flag): array => [$routeName, $flag],
        array_keys(releaseScopeGatedRoutes()),
        releaseScopeGatedRoutes(),
    ));

    it('gates every public v1 route, token endpoint included, with integrations_api', function () {
        $router = app(Router::class);
        $public = array_filter($router->getRoutes()->getRoutes(), static fn ($route): bool => str_starts_with((string) $route->getName(), 'public.v1.'));

        expect($public)->not->toBeEmpty();

        foreach ($public as $route) {
            expect($router->gatherRouteMiddleware($route))->toContain(EnsureFeatureEnabled::class.':integrations_api');
        }
    });

    it('answers 404 feature_disabled in core and something else in full', function (string $method, string $uri, string $flag) {
        $headers = Accounts::headers(Accounts::owner());

        $this->releaseScope(ReleaseScope::Core);

        $this->json($method, $uri, [], $headers)
            ->assertNotFound()
            ->assertJsonPath('code', 'feature_disabled')
            ->assertJsonPath('details.feature', $flag);

        $this->releaseScope(ReleaseScope::Full);

        expect($this->json($method, $uri, [], $headers)->json('code'))->not->toBe('feature_disabled');
    })->with([
        'team members' => ['GET', '/api/app/v1/team/members', 'team_management'],
        'vendors' => ['GET', '/api/app/v1/vendors', 'vendor_directory'],
        'api clients' => ['GET', '/api/app/v1/integrations/api-clients', 'integrations_api'],
        'exports' => ['POST', '/api/app/v1/integrations/exports', 'csv_import_export'],
        'custom quote (guest)' => ['GET', '/api/app/v1/plans/custom-quote', 'custom_plan_quote'],
        'coupon validation' => ['POST', '/api/app/v1/billing/coupons/validate', 'coupons'],
        // Unknown competition ids still answer feature_disabled: the gate runs before binding.
        'bafo round' => ['POST', '/api/app/v1/competitions/01J0000000000000000000ZZZZ/bafo-round', 'bafo_round'],
        'extend' => ['POST', '/api/app/v1/competitions/01J0000000000000000000ZZZZ/extend', 'extend_competition'],
        'sponsorship checkout' => ['POST', '/api/app/v1/competitions/01J0000000000000000000ZZZZ/sponsorship/checkout', 'sponsorship'],
    ]);

    it('hides the whole public API in core and authenticates it in full', function () {
        $this->releaseScope(ReleaseScope::Core);

        $this->getJson('/api/public/v1/ping')->assertNotFound()->assertJsonPath('code', 'feature_disabled');
        $this->getJson('/api/public/v1/openapi.yaml')->assertNotFound()->assertJsonPath('code', 'feature_disabled');
        $this->postJson('/api/public/v1/oauth/token', ['grant_type' => 'client_credentials'])
            ->assertNotFound()
            ->assertJsonPath('code', 'feature_disabled')
            ->assertJsonPath('error', 'feature_disabled');

        $this->releaseScope(ReleaseScope::Full);

        // Back to Integrations' own 401 (`invalid_token`) once the surface exists again.
        expect($this->getJson('/api/public/v1/ping')->assertUnauthorized()->json('code'))->not->toBe('feature_disabled');
        $this->get('/api/public/v1/openapi.yaml')->assertOk();
    });
});

describe('existing records keep rendering (§1.5 read-only exceptions)', function () {
    it('carries no feature gate on the endpoints that stay in core', function (string $routeName) {
        $route = app(Router::class)->getRoutes()->getByName($routeName);
        $gates = array_filter($route?->middleware() ?? [], static fn (string $m): bool => str_starts_with($m, 'feature:'));

        expect($route)->not->toBeNull()
            ->and($gates)->toBe([]);
    })->with([
        'app.v1.auth.team-invitations.lookup',
        'app.v1.auth.team-invitations.accept',
        'app.v1.competitions.sponsorship.show',
        'app.v1.competitions.sponsorship.update',
        'app.v1.competitions.sponsorship.quote',
        'app.v1.billing.vouchers.index',
        'app.v1.billing.invoices.index',
        'app.v1.billing.invoices.show',
        'app.v1.billing.invoices.pdf',
        'app.v1.plans.index',
        'app.v1.files.download',
        'app.v1.home',
        'app.v1.competitions.show',
        'app.v1.competitions.live',
        'app.v1.competitions.offers.store',
        'app.v1.competitions.publish',
        'app.v1.competitions.close',
        'app.v1.competitions.report',
        'app.v1.competitions.invitations.store',
    ]);

    it('serves plans, vouchers and invoices in core', function () {
        $headers = Accounts::headers(Accounts::owner());
        $this->releaseScope(ReleaseScope::Core);

        $this->getJson('/api/app/v1/plans')->assertOk();

        expect($this->getJson('/api/app/v1/billing/vouchers', $headers)->json('code'))->not->toBe('feature_disabled')
            ->and($this->getJson('/api/app/v1/billing/invoices', $headers)->json('code'))->not->toBe('feature_disabled');
    });
});

describe('platform:release-scope', function () {
    it('sets the scope through UpdateAppSetting as the system actor and prints the flags', function () {
        $this->artisan('platform:release-scope core')
            ->expectsOutputToContain('Release scope set to [core].')
            ->expectsOutputToContain('release_scope')
            ->expectsOutputToContain('bafo_round')
            ->assertSuccessful();

        $entry = AuditLog::query()->where('action', 'setting.updated')->sole();

        expect(app(FeatureFlags::class)->scope())->toBe(ReleaseScope::Core)
            ->and($entry->actor_type)->toBe(ActorType::System)
            ->and($entry->changes)->toEqual(['platform.release_scope' => ['from' => 'full', 'to' => 'core']]);

        $this->artisan('platform:release-scope full')->assertSuccessful();

        expect(app(FeatureFlags::class)->scope())->toBe(ReleaseScope::Full);
    });

    it('prints the current scope without an argument and refuses unknown values', function () {
        $this->releaseScope(ReleaseScope::Core);

        // One output line satisfies one expectation: the scope row reads "release_scope … core".
        $this->artisan('platform:release-scope')
            ->expectsOutputToContain('core')
            ->expectsOutputToContain('bafo_round')
            ->doesntExpectOutputToContain('Release scope set to')
            ->assertSuccessful();

        $this->artisan('platform:release-scope everything')
            ->expectsOutputToContain('Unknown release scope [everything]')
            ->assertFailed();

        expect(app(FeatureFlags::class)->scope())->toBe(ReleaseScope::Core)
            ->and(AuditLog::query()->where('action', 'setting.updated')->count())->toBe(0);
    });
});

describe('admin settings page', function () {
    beforeEach(function () {
        $this->admin = AdminPanel::signIn();
    });

    it('lists platform.release_scope and saves it through UpdateAppSetting', function () {
        Livewire::test(ManageSettings::class)
            ->assertSee('platform.release_scope')
            ->assertSchemaStateSet(['platform__release_scope' => 'full'], 'form')
            ->fillForm(['platform__release_scope' => 'core'], 'form')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        expect(app(FeatureFlags::class)->scope())->toBe(ReleaseScope::Core)
            ->and(AuditLog::query()->where('action', 'setting.updated')->sole()->actor_id)->toBe($this->admin->id);

        $this->getJson('/api/app/v1/app-config')->assertJsonPath('data.features.release_scope', 'core');
    });
});
