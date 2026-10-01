<?php

declare(strict_types=1);

use Illuminate\Routing\Router;

it('registers the Platform endpoints of API.md §1.1', function (string $name, string $method, string $uri, bool $guest) {
    $route = app(Router::class)->getRoutes()->getByName($name);

    expect($route)->not->toBeNull()
        ->and($route?->uri())->toBe($uri)
        ->and($route?->methods())->toContain($method)
        ->and(in_array('auth:sanctum', $route?->excludedMiddleware() ?? [], true))->toBe($guest);
})->with([
    ['app.v1.app-config', 'GET', 'api/app/v1/app-config', true],
    ['app.v1.time', 'GET', 'api/app/v1/time', true],
    ['app.v1.health', 'GET', 'api/app/v1/health', true],
    ['app.v1.contact.store', 'POST', 'api/app/v1/contact', true],
    ['app.v1.legal.show', 'GET', 'api/app/v1/legal/{code}', true],
    ['app.v1.files.download', 'GET', 'api/app/v1/files/{file}/download', false],
]);

it('rate limits the contact form with guest-forms', function () {
    expect(app(Router::class)->getRoutes()->getByName('app.v1.contact.store')?->middleware())
        ->toContain('throttle:guest-forms');
});
