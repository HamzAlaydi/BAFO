<?php

declare(strict_types=1);

/*
 * CORS for the browser client (config/cors.php): the web dashboard runs on another origin, so it
 * can only read the response headers listed in exposed_headers and send the request headers
 * listed in allowed_headers.
 */

beforeEach(function (): void {
    config(['cors.allowed_origins' => ['http://localhost:3000']]);
});

it('exposes the response headers the web client reads', function (): void {
    $response = $this->withHeaders(['Origin' => 'http://localhost:3000'])->getJson('/api/app/v1/lookups')->assertOk();

    $exposed = array_map(
        static fn (string $header): string => strtolower(trim($header)),
        explode(',', (string) $response->headers->get('Access-Control-Expose-Headers')),
    );

    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('http://localhost:3000')
        ->and($exposed)->toContain('etag', 'retry-after', 'idempotent-replayed', 'content-disposition', 'x-request-id');
});

it('allows the conditional and idempotent request headers in a preflight', function (): void {
    $response = $this->call('OPTIONS', '/api/app/v1/lookups', server: [
        'HTTP_ORIGIN' => 'http://localhost:3000',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'if-none-match, idempotency-key, x-platform, accept-language',
    ]);

    $allowed = strtolower((string) $response->headers->get('Access-Control-Allow-Headers'));

    expect($response->getStatusCode())->toBeIn([200, 204])
        ->and($allowed)->toContain('if-none-match', 'idempotency-key', 'x-platform', 'accept-language');
});

it('does not grant CORS to an unknown origin', function (): void {
    $response = $this->withHeaders(['Origin' => 'https://evil.example'])->getJson('/api/app/v1/lookups');

    // A single configured origin is echoed as is, so the browser still refuses the foreign page.
    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example');
});
