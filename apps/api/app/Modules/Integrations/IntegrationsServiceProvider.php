<?php

declare(strict_types=1);

namespace App\Modules\Integrations;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Console\Commands\DispatchWebhooksCommand;
use App\Modules\Integrations\Console\Commands\OpenApiCommand;
use App\Modules\Integrations\Console\Commands\PruneIntegrationsCommand;
use App\Modules\Integrations\Data\ApiClientContext;
use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Http\Middleware\AuthenticatePublicApiClient;
use App\Modules\Integrations\Http\Middleware\EnsureApiScope;
use App\Modules\Integrations\Http\Middleware\EnsureIntegrationsAccess;
use App\Modules\Integrations\Http\Middleware\ShapeOAuthErrors;
use App\Modules\Integrations\Listeners\LinkVendorsToOrganization;
use App\Modules\Integrations\Listeners\RevokeOrganizationApiAccess;
use App\Modules\Integrations\Listeners\WriteAwardWebhooks;
use App\Modules\Integrations\Listeners\WriteCompetitionWebhooks;
use App\Modules\Integrations\Listeners\WriteInvitationWebhooks;
use App\Modules\Integrations\Listeners\WriteOfferWebhooks;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ExportJob;
use App\Modules\Integrations\Models\ImportJob;
use App\Modules\Integrations\Models\Vendor;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Policies\ApiClientPolicy;
use App\Modules\Integrations\Policies\ExportJobPolicy;
use App\Modules\Integrations\Policies\ImportJobPolicy;
use App\Modules\Integrations\Policies\VendorPolicy;
use App\Modules\Integrations\Policies\WebhookDeliveryPolicy;
use App\Modules\Integrations\Policies\WebhookEndpointPolicy;
use App\Modules\Integrations\Services\Webhooks\HostResolver;
use App\Modules\Integrations\Services\Webhooks\SystemHostResolver;
use App\Support\Files\File;
use App\Support\Files\FileAccessRegistry;
use App\Support\Files\FilePurpose;
use App\Support\Modules\ModuleServiceProvider;
use Carbon\CarbonInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Passport\Passport;

/**
 * Integrations module.
 *
 * The public ERP API (R1): API clients and keys, the Passport client-credentials recipe,
 * the `api.client` / `api.scope` middleware, the vendor directory, the webhook outbox with
 * signed deliveries, CSV/XLSX vendor import and exports, and the public v1 OpenAPI document.
 *
 * HTTP routes: routes/app_v1/integrations.php and routes/public_v1/integrations.php; the API
 * reference page is Routes/web.php (`/docs/api`).
 * Conventions (config, migrations, views, web routes): App\Support\Modules\ModuleServiceProvider.
 */
final class IntegrationsServiceProvider extends ModuleServiceProvider
{
    /**
     * Domain events that write the webhook outbox (ARCHITECTURE §10, API.md §4.1), listened to by
     * class-string so the producing module's classes are never required here. Synchronous: they
     * run inside the producer's transaction.
     *
     * @var list<array{0: string, 1: class-string, 2: string}>
     */
    public const array WEBHOOK_LISTENERS = [
        ['App\\Modules\\Competitions\\Events\\CompetitionPublished', WriteCompetitionWebhooks::class, 'published'],
        ['App\\Modules\\Competitions\\Events\\CompetitionExtended', WriteCompetitionWebhooks::class, 'extended'],
        ['App\\Modules\\Competitions\\Events\\CompetitionClosed', WriteCompetitionWebhooks::class, 'closed'],
        ['App\\Modules\\Bidding\\Events\\OffersUnsealed', WriteCompetitionWebhooks::class, 'offersOpened'],
        ['App\\Modules\\Competitions\\Events\\CompetitionCancelled', WriteCompetitionWebhooks::class, 'cancelled'],
        ['App\\Modules\\Competitions\\Events\\CompetitionClosedWithoutAward', WriteCompetitionWebhooks::class, 'notAwarded'],
        ['App\\Modules\\Competitions\\Events\\InvitationJoined', WriteInvitationWebhooks::class, 'accepted'],
        ['App\\Modules\\Competitions\\Events\\InvitationDeclined', WriteInvitationWebhooks::class, 'declined'],
        ['App\\Modules\\Bidding\\Events\\OfferAccepted', WriteOfferWebhooks::class, 'accepted'],
        ['App\\Modules\\Bidding\\Events\\AwardIssued', WriteAwardWebhooks::class, 'issued'],
        ['App\\Modules\\Bidding\\Events\\AwardRevoked', WriteAwardWebhooks::class, 'revoked'],
    ];

    /**
     * Queued reactions to Identity events (ARCHITECTURE §10).
     *
     * @var array<string, class-string>
     */
    public const array IDENTITY_LISTENERS = [
        'App\\Modules\\Identity\\Events\\EmailVerified' => LinkVendorsToOrganization::class,
        'App\\Modules\\Identity\\Events\\AccountDeleted' => RevokeOrganizationApiAccess::class,
    ];

    /**
     * Appended to the `public_v1` group, in this order (ARCHITECTURE §2.2 item 7).
     *
     * @var list<string>
     */
    public const array PUBLIC_V1_MIDDLEWARE = [
        AuthenticatePublicApiClient::class,
        'throttle:public-api',
        SubstituteBindings::class,
    ];

    /**
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        Vendor::class => VendorPolicy::class,
        ApiClient::class => ApiClientPolicy::class,
        WebhookEndpoint::class => WebhookEndpointPolicy::class,
        WebhookDelivery::class => WebhookDeliveryPolicy::class,
        ImportJob::class => ImportJobPolicy::class,
        ExportJob::class => ExportJobPolicy::class,
    ];

    public function register(): void
    {
        parent::register();

        // §14.2: the default /oauth/* routes are not registered; the token endpoint is ours.
        Passport::ignoreRoutes();

        $this->app->singleton(HostResolver::class, SystemHostResolver::class);

        Relation::morphMap([
            'vendor' => Vendor::class,
            'api_client' => ApiClient::class,
            'webhook_endpoint' => WebhookEndpoint::class,
        ]);
    }

    public function boot(): void
    {
        parent::boot();

        Passport::tokensExpireIn(CarbonInterval::minutes((int) config('bafo.integrations.oauth.token_ttl_minutes', 30)));
        Passport::tokensCan(ApiScope::descriptions());

        $this->registerMiddleware();
        $this->registerRateLimiters();
        $this->registerListeners();
        $this->registerFileAccessRules();

        if ($this->app->runningInConsole()) {
            $this->commands([DispatchWebhooksCommand::class, PruneIntegrationsCommand::class, OpenApiCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('integrations:dispatch-webhooks')->everyMinute()->withoutOverlapping()->onOneServer();
            $schedule->command('integrations:prune')->daily()->onOneServer();
        });
    }

    private function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('api.client', AuthenticatePublicApiClient::class);
        $router->aliasMiddleware('api.scope', EnsureApiScope::class);
        $router->aliasMiddleware('integrations.access', EnsureIntegrationsAccess::class);

        // The HTTP kernel writes its groups and priority to the router when it is resolved
        // (bootstrap/app.php): configure the router now and again then, so the result is the same
        // whichever happens first. Both steps are idempotent.
        self::configureRouter($router);
        $this->app->afterResolving(HttpKernel::class, fn () => self::configureRouter($this->app->make(Router::class)));
    }

    /**
     * Appends `api.client`, `throttle:public-api` and SubstituteBindings to `public_v1`, and ranks
     * ShapeOAuthErrors first so it wraps `throttle:oauth-token` on the token endpoint (the router
     * sorts the throttle ahead of unranked route middleware otherwise).
     */
    private static function configureRouter(Router $router): void
    {
        foreach (self::PUBLIC_V1_MIDDLEWARE as $middleware) {
            $router->pushMiddlewareToGroup('public_v1', $middleware);
        }

        if (! in_array(ShapeOAuthErrors::class, $router->middlewarePriority, true)) {
            array_unshift($router->middlewarePriority, ShapeOAuthErrors::class);
        }
    }

    /**
     * §14.3: `public-api` per client (GET 600/min, other methods 300/min) and per organization
     * (1500/min); `oauth-token` 20/min per IP and `client_id`. 429 `too_many_requests` with
     * `Retry-After`.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('public-api', static function (Request $request): array {
            $context = $request->attributes->get(ApiClientContext::ATTRIBUTE);

            if (! $context instanceof ApiClientContext) {
                return [Limit::perMinute((int) config('bafo.integrations.rate_limits.per_minute_write'))->by('public-api:ip:'.$request->ip())];
            }

            $client = $context->client;
            $read = $request->isMethodSafe();

            return [
                Limit::perMinute((int) config('bafo.integrations.rate_limits.'.($read ? 'per_minute_read' : 'per_minute_write')))
                    ->by('public-api:client:'.$client->id.':'.($read ? 'read' : 'write')),
                Limit::perMinute((int) config('bafo.integrations.rate_limits.per_minute_organization'))
                    ->by('public-api:organization:'.$client->organization_id),
            ];
        });

        RateLimiter::for('oauth-token', static function (Request $request): Limit {
            $clientId = $request->getUser() ?? $request->input('client_id');

            return Limit::perMinute((int) config('bafo.integrations.rate_limits.token_per_minute'))
                ->by('oauth-token:'.$request->ip().':'.(is_string($clientId) ? $clientId : ''));
        });
    }

    private function registerListeners(): void
    {
        foreach (self::WEBHOOK_LISTENERS as [$event, $listener, $method]) {
            Event::listen($event, [$listener, $method]);
        }

        foreach (self::IDENTITY_LISTENERS as $event => $listener) {
            Event::listen($event, $listener);
        }
    }

    /**
     * §8.5: import sources, import error files and exports: members of the owning organization
     * with `integrations.manage`.
     */
    private function registerFileAccessRules(): void
    {
        $rule = static fn (Authenticatable $user, File $file): bool => $user instanceof User
            && $file->organization_id !== null
            && $user->membership?->organization_id === $file->organization_id
            && $user->hasPermission(Permission::IntegrationsManage);

        $registry = $this->app->make(FileAccessRegistry::class);

        foreach ([FilePurpose::ImportSource, FilePurpose::ImportErrors, FilePurpose::Export] as $purpose) {
            $registry->register($purpose, $rule);
        }
    }
}
