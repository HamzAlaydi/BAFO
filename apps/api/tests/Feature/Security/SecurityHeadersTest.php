<?php

declare(strict_types=1);

use App\Support\Files\File;
use App\Support\Files\FilePurpose;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Identity\Accounts;

/*
 * Security review (docs/build/SECURITY_REVIEW.md) S-05: response hardening headers on the API
 * (success, error and download responses) and on the admin panel.
 */

it('hardens every API response, including errors', function (string $method, string $uri, int $status) {
    $response = $this->json($method, $uri)->assertStatus($status);

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('Referrer-Policy'))->toBe('same-origin')
        ->and($response->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; frame-ancestors 'none'")
        ->and($response->headers->has('Strict-Transport-Security'))->toBeFalse();
})->with([
    'guest endpoint' => ['GET', '/api/app/v1/time', 200],
    'unauthenticated' => ['GET', '/api/app/v1/me', 401],
    'unknown route' => ['GET', '/api/app/v1/nope', 404],
    'public API without a key' => ['GET', '/api/public/v1/ping', 401],
    'broadcast auth without a token' => ['POST', '/broadcasting/auth', 401],
]);

it('adds HSTS on HTTPS requests only', function () {
    $response = $this->getJson('https://localhost/api/app/v1/time')->assertOk();

    expect($response->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains');
});

it('keeps the download headers of private files and adds the CSP', function () {
    $owner = Accounts::owner();
    $file = File::factory()->purpose(FilePurpose::OrganizationProfile)->create(['organization_id' => $owner->membership->organization_id]);
    Storage::disk('private')->put($file->path, '%PDF-1.4 profile');

    $response = $this->get('/api/app/v1/files/'.$file->public_id.'/download', Accounts::headers($owner))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; frame-ancestors 'none'");
});

it('lets the admin panel frame itself only and keeps its own content policy', function () {
    $response = $this->get('/admin/login')->assertOk();

    expect($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Content-Security-Policy'))->toBeNull();
});
