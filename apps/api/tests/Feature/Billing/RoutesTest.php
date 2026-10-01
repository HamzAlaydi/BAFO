<?php

declare(strict_types=1);

use Illuminate\Routing\Router;

it('registers the Billing endpoints of API.md §1.7', function (string $name, string $method, string $uri, bool $guest) {
    $route = app(Router::class)->getRoutes()->getByName($name);

    expect($route)->not->toBeNull()
        ->and($route?->uri())->toBe($uri)
        ->and($route?->methods())->toContain($method)
        ->and(in_array('auth:sanctum', $route?->excludedMiddleware() ?? [], true))->toBe($guest);
})->with([
    ['app.v1.plans.index', 'GET', 'api/app/v1/plans', true],
    ['app.v1.plans.custom-quote', 'GET', 'api/app/v1/plans/custom-quote', true],
    ['app.v1.billing.subscription', 'GET', 'api/app/v1/billing/subscription', false],
    ['app.v1.billing.trial', 'POST', 'api/app/v1/billing/trial', false],
    ['app.v1.billing.coupons.validate', 'POST', 'api/app/v1/billing/coupons/validate', false],
    ['app.v1.billing.checkout.subscription', 'POST', 'api/app/v1/billing/checkout/subscription', false],
    ['app.v1.billing.payments.show', 'GET', 'api/app/v1/billing/payments/{payment}', false],
    ['app.v1.billing.payments.verify', 'POST', 'api/app/v1/billing/payments/{payment}/verify', false],
    ['app.v1.billing.invoices.index', 'GET', 'api/app/v1/billing/invoices', false],
    ['app.v1.billing.invoices.show', 'GET', 'api/app/v1/billing/invoices/{invoice}', false],
    ['app.v1.billing.invoices.pdf', 'GET', 'api/app/v1/billing/invoices/{invoice}/pdf', false],
    ['app.v1.billing.vouchers.index', 'GET', 'api/app/v1/billing/vouchers', false],
    ['app.v1.billing.gateway-webhooks', 'POST', 'api/app/v1/billing/gateway-webhooks/{gateway}', true],
    ['app.v1.competitions.sponsorship.show', 'GET', 'api/app/v1/competitions/{competition}/sponsorship', false],
    ['app.v1.competitions.sponsorship.update', 'PUT', 'api/app/v1/competitions/{competition}/sponsorship', false],
    ['app.v1.competitions.sponsorship.quote', 'GET', 'api/app/v1/competitions/{competition}/sponsorship/quote', false],
    ['app.v1.competitions.sponsorship.checkout', 'POST', 'api/app/v1/competitions/{competition}/sponsorship/checkout', false],
]);

it('makes the Idempotency-Key optional on checkout', function (string $name) {
    expect(app(Router::class)->getRoutes()->getByName($name)?->gatherMiddleware())->toContain('idempotent:optional');
})->with(['app.v1.billing.checkout.subscription', 'app.v1.competitions.sponsorship.checkout']);

it('serves the fake hosted page on the web middleware', function () {
    $route = app(Router::class)->getRoutes()->getByName('billing.fake-pay.show');

    expect($route?->uri())->toBe('pay/fake/{payment}')
        ->and($route?->gatherMiddleware())->toContain('web')
        ->and(app(Router::class)->getRoutes()->getByName('billing.fake-pay.approve')?->methods())->toContain('POST');
});
