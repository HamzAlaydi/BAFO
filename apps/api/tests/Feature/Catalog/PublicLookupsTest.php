<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Region;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\ApiScope;
use Illuminate\Routing\Router;
use Tests\Support\Identity\Accounts;
use Tests\Support\Identity\PublicApi;

beforeEach(function () {
    Accounts::seedCatalog();
});

function catalogPublicOrganization(): Organization
{
    return Organization::factory()->create(['region_id' => Region::query()->where('code', 'RIY')->value('id')]);
}

it('registers the public lookups endpoint with the lookups:read scope', function () {
    $route = app(Router::class)->getRoutes()->getByName('public.v1.lookups.show');

    expect($route?->uri())->toBe('api/public/v1/lookups/{type}')
        ->and($route?->middleware())->toContain('api.scope:lookups:read');
});

it('returns the regions with Arabic and English names', function () {
    $headers = PublicApi::headers(catalogPublicOrganization(), [ApiScope::LookupsRead]);

    $response = $this->getJson('/api/public/v1/lookups/regions', $headers)
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'code', 'name' => ['ar', 'en']]]])
        ->assertJsonMissingPath('meta.server_time');

    expect($response->json('data.0'))->toMatchArray([
        'code' => 'RIY',
        'name' => ['ar' => 'الرياض', 'en' => 'Riyadh'],
    ])->and($response->json('data'))->toHaveCount(13);
});

it('adds the type fields for categories and close reasons', function () {
    $headers = PublicApi::headers(catalogPublicOrganization(), [ApiScope::LookupsRead]);

    $this->getJson('/api/public/v1/lookups/categories', $headers)
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'code', 'name' => ['ar', 'en'], 'is_other', 'auction_allowed']]]);

    $reasons = $this->getJson('/api/public/v1/lookups/close-reasons?kind=cancel', $headers)
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'code', 'name' => ['ar', 'en'], 'kind', 'requires_note']]])
        ->json('data');

    expect(collect($reasons)->pluck('kind')->unique()->all())->toBe(['cancel']);
});

it('does not serve presets on the public API', function () {
    $headers = PublicApi::headers(catalogPublicOrganization(), [ApiScope::LookupsRead]);

    $this->getJson('/api/public/v1/lookups/presets', $headers)->assertNotFound();
});

it('requires the lookups:read scope', function () {
    $headers = PublicApi::headers(catalogPublicOrganization(), [ApiScope::OrganizationRead]);

    $this->getJson('/api/public/v1/lookups/regions', $headers)
        ->assertForbidden()
        ->assertJsonPath('code', 'insufficient_scope')
        ->assertJsonPath('details.required_scope', 'lookups:read');
});
