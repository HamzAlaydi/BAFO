<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Catalog\Models\Region;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\ApiScope;
use Illuminate\Routing\Router;
use Tests\Support\Identity\Accounts;
use Tests\Support\Identity\PublicApi;

beforeEach(function () {
    Accounts::seedCatalog();
});

it('registers the endpoint with the organization:read scope', function () {
    $route = app(Router::class)->getRoutes()->getByName('public.v1.organization');

    expect($route?->uri())->toBe('api/public/v1/organization')
        ->and($route?->middleware())->toContain('api.scope:organization:read');
});

it("returns the API client's own organization", function () {
    $organization = Organization::factory()->auctionEnabled()->create(['region_id' => Region::query()->where('code', 'MAK')->value('id')]);
    Organization::factory()->create();
    Subscription::factory()->create([
        'organization_id' => $organization->id,
        'plan_id' => Plan::factory()->create(['code' => 'plus'])->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);

    $this->getJson('/api/public/v1/organization', PublicApi::headers($organization, [ApiScope::OrganizationRead]))
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'id', 'name', 'legal_name_ar', 'legal_name_en', 'cr_number', 'vat_number', 'national_address', 'region' => ['code', 'name' => ['ar', 'en']],
            'city', 'website', 'email', 'phone', 'features' => ['auction_enabled', 'sponsorship_enabled'], 'subscription' => ['plan_code', 'status', 'ends_at'],
        ]])
        ->assertJsonMissingPath('meta.server_time')
        ->assertJsonPath('data.id', $organization->public_id)
        ->assertJsonPath('data.region', ['code' => 'MAK', 'name' => ['ar' => 'مكة المكرمة', 'en' => 'Makkah']])
        ->assertJsonPath('data.features', ['auction_enabled' => true, 'sponsorship_enabled' => false])
        ->assertJsonPath('data.subscription.plan_code', 'plus')
        ->assertJsonPath('data.subscription.status', 'active');
});

it('returns a null subscription without a plan', function () {
    $organization = Organization::factory()->create(['region_id' => Region::query()->where('code', 'RIY')->value('id')]);

    $this->getJson('/api/public/v1/organization', PublicApi::headers($organization, [ApiScope::OrganizationRead]))
        ->assertOk()
        ->assertJsonPath('data.subscription', null);
});

it('requires the organization:read scope', function () {
    $organization = Organization::factory()->create(['region_id' => Region::query()->where('code', 'RIY')->value('id')]);

    $this->getJson('/api/public/v1/organization', PublicApi::headers($organization, [ApiScope::LookupsRead]))
        ->assertForbidden()
        ->assertJsonPath('code', 'insufficient_scope');
});
