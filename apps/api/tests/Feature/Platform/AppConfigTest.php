<?php

declare(strict_types=1);

use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Modules\Platform\Models\LegalDocument;
use App\Support\Settings\Settings;

it('returns the AppConfig resource without a token', function () {
    $response = $this->getJson('/api/app/v1/app-config');

    $response->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonStructure([
            'data' => [
                'min_version' => ['ios', 'android'],
                'latest_version' => ['ios', 'android'],
                'store_links' => ['ios', 'android'],
                'maintenance' => ['enabled', 'message'],
                'support' => ['email', 'phone', 'whatsapp'],
                'realtime' => ['key', 'host', 'port', 'scheme'],
                'legal' => ['terms', 'privacy', 'competition_rules'],
                // RELEASE_SCOPE.md §1.4; the values per scope are in ReleaseScopeTest.
                'features' => ['release_scope', 'sponsorship', 'flags'],
                'currency',
                'vat_rate_bp',
                'supported_locales',
                'server_time',
            ],
            'meta' => ['server_time'],
        ])
        ->assertJsonPath('data.min_version', ['ios' => '1.0.0', 'android' => '1.0.0'])
        ->assertJsonPath('data.store_links', ['ios' => '', 'android' => ''])
        ->assertJsonPath('data.support', ['email' => '', 'phone' => '', 'whatsapp' => ''])
        ->assertJsonPath('data.maintenance.enabled', false)
        ->assertJsonPath('data.legal', ['terms' => null, 'privacy' => null, 'competition_rules' => null])
        ->assertJsonPath('data.features.sponsorship', true)
        ->assertJsonPath('data.currency', 'SAR')
        ->assertJsonPath('data.vat_rate_bp', 1500)
        ->assertJsonPath('data.supported_locales', ['ar', 'en']);

    expect($response->json('data.server_time'))->toBeIso8601Utc();
});

it('serves the version gates from the settings store', function () {
    $settings = app(Settings::class);
    $settings->set('app.min_version.ios', '1.3.0', null);
    $settings->set('app.latest_version.android', '2.0.1', null);

    $this->getJson('/api/app/v1/app-config')
        ->assertJsonPath('data.min_version', ['ios' => '1.3.0', 'android' => '1.0.0'])
        ->assertJsonPath('data.latest_version.android', '2.0.1');
});

it('serves support contacts, store links and the sponsorship switch from settings', function () {
    $settings = app(Settings::class);
    $settings->set('app.support', ['email' => 'help@bafo.test', 'phone' => '+966500000000', 'whatsapp' => ''], null);
    $settings->set('app.store_links', ['ios' => 'https://apps.apple.test/bafo', 'android' => ''], null);
    $settings->set('sponsorship.enabled', false, null);

    $this->getJson('/api/app/v1/app-config')
        ->assertJsonPath('data.support.email', 'help@bafo.test')
        ->assertJsonPath('data.store_links.ios', 'https://apps.apple.test/bafo')
        ->assertJsonPath('data.features.sponsorship', false);
});

it('hands out the realtime connection from configuration', function () {
    config()->set('bafo.platform.realtime', ['key' => 'local-key', 'host' => 'localhost', 'port' => 8085, 'scheme' => 'http']);

    $this->getJson('/api/app/v1/app-config')
        ->assertJsonPath('data.realtime', ['key' => 'local-key', 'host' => 'localhost', 'port' => 8085, 'scheme' => 'http']);
});

it('localises the maintenance message and stays reachable during maintenance', function () {
    $settings = app(Settings::class);
    $settings->set('app.maintenance.enabled', true, null);
    $settings->set('app.maintenance.message', ['ar' => 'صيانة مجدولة', 'en' => 'Scheduled maintenance'], null);

    $this->getJson('/api/app/v1/app-config')
        ->assertOk()
        ->assertJsonPath('data.maintenance', ['enabled' => true, 'message' => 'صيانة مجدولة']);

    $this->getJson('/api/app/v1/app-config', ['Accept-Language' => 'en'])
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('data.maintenance.message', 'Scheduled maintenance');
});

it('announces the latest published legal versions in the request locale', function () {
    LegalDocument::factory()->forCode(LegalDocumentCode::Terms, 'ar', '2026-10-01')->create();
    LegalDocument::factory()->forCode(LegalDocumentCode::Terms, 'ar', '2026-11-01')->create(['published_at' => now()->subHour()]);
    LegalDocument::factory()->forCode(LegalDocumentCode::Terms, 'ar', '2027-01-01')->draft()->create();
    LegalDocument::factory()->forCode(LegalDocumentCode::Privacy, 'en', '2026-10-01')->create();

    $this->getJson('/api/app/v1/app-config')
        ->assertJsonPath('data.legal', [
            'terms' => ['version' => '2026-11-01'],
            'privacy' => null,
            'competition_rules' => null,
        ]);

    $this->getJson('/api/app/v1/app-config', ['Accept-Language' => 'en'])
        ->assertJsonPath('data.legal.privacy', ['version' => '2026-10-01'])
        ->assertJsonPath('data.legal.terms', null);
});

it('serves the server time without a token', function () {
    $response = $this->getJson('/api/app/v1/time')->assertOk();

    expect($response->json('data.server_time'))
        ->toBeIso8601Utc()
        ->toMatch('/\.\d{3}Z$/');
});
