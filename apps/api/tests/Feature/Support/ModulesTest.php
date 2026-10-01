<?php

declare(strict_types=1);

use App\Modules\Admin\AdminServiceProvider;
use App\Modules\Bidding\BiddingServiceProvider;
use App\Modules\Billing\BillingServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Competitions\CompetitionsServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Integrations\IntegrationsServiceProvider;
use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Platform\PlatformServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;

it('discovers and registers every module provider', function (string $provider) {
    expect($this->app->getProviders($provider))->toHaveCount(1);
})->with([
    PlatformServiceProvider::class,
    CatalogServiceProvider::class,
    IdentityServiceProvider::class,
    IntegrationsServiceProvider::class,
    CompetitionsServiceProvider::class,
    BiddingServiceProvider::class,
    BillingServiceProvider::class,
    NotificationsServiceProvider::class,
    AdminServiceProvider::class,
]);

it('merges module config under bafo.<module>', function () {
    expect(config('bafo.platform.realtime'))->toHaveKeys(['key', 'host', 'port', 'scheme'])
        ->and(config('bafo.platform.realtime.port'))->toBe(8085);
});

it('defines the shared rate limiters', function (string $limiter) {
    expect(RateLimiter::limiter($limiter))->not->toBeNull();
})->with(['app', 'auth', 'guest-forms', 'guest-actions']);

it('loads the module api route files under their prefixes', function () {
    $router = $this->app->make(Router::class);

    expect($router->getRoutes()->getByName('app.v1.health')?->uri())->toBe('api/app/v1/health')
        ->and($router->getRoutes()->getByName('app.v1.app-config')?->uri())->toBe('api/app/v1/app-config')
        ->and($router->getRoutes()->getByName('app.v1.time')?->uri())->toBe('api/app/v1/time');
});
