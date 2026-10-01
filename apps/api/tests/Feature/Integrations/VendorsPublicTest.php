<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Region;
use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Enums\VendorSource;
use App\Modules\Integrations\Enums\VendorStatus;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\Vendor;
use Tests\Support\Integrations\IntegrationsFixtures;

/**
 * @return array{0: array<string, string>, 1: ApiClient}
 */
function integrationsVendorClient(): array
{
    [$plain, $client] = IntegrationsFixtures::apiKey(scopes: [ApiScope::VendorsRead, ApiScope::VendorsWrite]);

    return [IntegrationsFixtures::bearer($plain), $client];
}

function integrationsSupplierRef(Vendor $vendor, string $system, string $value): void
{
    ExternalRef::factory()->create([
        'organization_id' => $vendor->organization_id, 'refable_type' => 'vendor', 'refable_id' => $vendor->id,
        'system' => $system, 'type' => 'supplier', 'value' => $value,
    ]);
}

it('creates a vendor with lookup codes, a Location header and the public shape', function () {
    [$headers, $client] = integrationsVendorClient();
    Region::factory()->create(['code' => 'RIY', 'name' => ['ar' => 'الرياض', 'en' => 'Riyadh']]);
    Category::factory()->create(['code' => 'it_hardware', 'name' => ['ar' => 'أجهزة', 'en' => 'Hardware']]);

    $response = test()->postJson('/api/public/v1/vendors', [
        'name' => 'شركة الريادة',
        'email' => 'sales@riyada.sa',
        'region_code' => 'RIY',
        'category_codes' => ['it_hardware'],
        'external_refs' => [['system' => 'sap_s4', 'type' => 'supplier', 'id' => '100045']],
    ], [...$headers, 'Idempotency-Key' => 'vendor-create-0001'])->assertCreated();

    $vendor = Vendor::query()->sole();

    $response->assertHeader('Location', url('/api/public/v1/vendors/'.$vendor->public_id))
        ->assertJsonPath('data.region', ['code' => 'RIY', 'name' => ['ar' => 'الرياض', 'en' => 'Riyadh']])
        ->assertJsonPath('data.categories', [['code' => 'it_hardware', 'name' => ['ar' => 'أجهزة', 'en' => 'Hardware']]])
        ->assertJsonPath('data.source', 'api')
        ->assertJsonMissingPath('meta.server_time');

    expect($vendor->organization_id)->toBe($client->organization_id)
        ->and($vendor->source)->toBe(VendorSource::Api)
        ->and(ExternalRef::query()->sole()->created_by_api_client_id)->toBe($client->id);
});

it('requires an Idempotency-Key on create', function () {
    [$headers] = integrationsVendorClient();

    test()->postJson('/api/public/v1/vendors', ['name' => 'x', 'email' => 'x@y.sa'], $headers)
        ->assertStatus(400)
        ->assertJsonPath('code', 'idempotency_key_required');
});

it('validates lookup codes', function () {
    [$headers] = integrationsVendorClient();

    test()->postJson('/api/public/v1/vendors', ['name' => 'x', 'email' => 'x@y.sa', 'region_code' => 'ZZZ', 'category_codes' => ['nope']],
        [...$headers, 'Idempotency-Key' => 'vendor-create-0002'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['region_code', 'category_codes.0']);
});

it('upserts by ERP key: 201 on create, 200 on replace', function () {
    [$headers, $client] = integrationsVendorClient();

    $created = test()->putJson('/api/public/v1/vendors/external/sap_s4/100045', [
        'name' => 'شركة الريادة', 'email' => 'sales@riyada.sa', 'city' => 'جدة', 'notes' => 'private',
    ], $headers)->assertCreated();

    $vendor = Vendor::query()->sole();
    $created->assertHeader('Location', url('/api/public/v1/vendors/'.$vendor->public_id))
        ->assertJsonPath('data.external_refs.0', ['system' => 'sap_s4', 'type' => 'supplier', 'id' => '100045', 'number' => null, 'url' => null]);

    $vendor->forceFill(['status' => VendorStatus::Blocked])->save();

    test()->putJson('/api/public/v1/vendors/external/sap_s4/100045', ['name' => 'Al Riyada', 'email' => 'new@riyada.sa'], $headers)
        ->assertOk()
        ->assertJsonPath('data.id', $vendor->public_id)
        ->assertJsonPath('data.name', 'Al Riyada')
        ->assertJsonPath('data.email', 'new@riyada.sa')
        ->assertJsonPath('data.city', null)
        ->assertJsonPath('data.notes', 'private')
        ->assertJsonPath('data.status', 'blocked');

    expect(Vendor::query()->count())->toBe(1)->and(ExternalRef::query()->count())->toBe(1);
});

it('attaches the ERP key to the vendor with the same e-mail', function () {
    [$headers, $client] = integrationsVendorClient();
    $vendor = Vendor::factory()->create(['organization_id' => $client->organization_id, 'email' => 'sales@riyada.sa']);

    test()->putJson('/api/public/v1/vendors/external/custom%3Amy_erp/V-1/7', ['name' => 'X', 'email' => 'sales@riyada.sa'], $headers)
        ->assertOk()
        ->assertJsonPath('data.id', $vendor->public_id)
        ->assertJsonPath('data.external_refs.0.system', 'custom:my_erp')
        ->assertJsonPath('data.external_refs.0.id', 'V-1/7');

    test()->getJson('/api/public/v1/vendors/external/custom%3Amy_erp/V-1/7', $headers)
        ->assertOk()
        ->assertJsonPath('data.id', $vendor->public_id);
});

it('validates the upsert path and body', function () {
    [$headers] = integrationsVendorClient();

    test()->putJson('/api/public/v1/vendors/external/SAP%20S4/1', ['email' => 'bad'], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['system', 'name', 'email']);
});

it('finds a vendor by ERP key or answers 404', function () {
    [$headers, $client] = integrationsVendorClient();
    $vendor = Vendor::factory()->create(['organization_id' => $client->organization_id]);
    integrationsSupplierRef($vendor, 'odoo', '55');
    $foreign = Vendor::factory()->create();
    integrationsSupplierRef($foreign, 'odoo', '66');

    test()->getJson('/api/public/v1/vendors/external/odoo/55', $headers)->assertOk()->assertJsonPath('data.id', $vendor->public_id);
    test()->getJson('/api/public/v1/vendors/external/odoo/66', $headers)->assertNotFound();
    test()->getJson('/api/public/v1/vendors/external/odoo/404', $headers)->assertNotFound();
});

it('lists with cursor pagination and the documented filters', function () {
    [$headers, $client] = integrationsVendorClient();
    $old = Vendor::factory()->create(['organization_id' => $client->organization_id, 'updated_at' => now()->subDays(3)]);
    $new = Vendor::factory()->create(['organization_id' => $client->organization_id, 'linked_organization_id' => $client->organization_id]);
    $blocked = Vendor::factory()->blocked()->create(['organization_id' => $client->organization_id, 'name' => 'Zeta Blocked']);
    integrationsSupplierRef($blocked, 'sap_s4', '9');
    Vendor::factory()->create();

    $page = test()->getJson('/api/public/v1/vendors?per_page=2', $headers)
        ->assertOk()
        ->assertJsonPath('meta.pagination.type', 'cursor')
        ->assertJsonPath('meta.pagination.has_more', true)
        ->assertJsonPath('data.0.id', $old->public_id);

    test()->getJson('/api/public/v1/vendors?per_page=2&cursor='.$page->json('meta.pagination.next_cursor'), $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.pagination.has_more', false);

    test()->getJson('/api/public/v1/vendors?updated_since='.urlencode(now()->subDay()->toIso8601String()), $headers)->assertJsonCount(2, 'data');
    test()->getJson('/api/public/v1/vendors?status=blocked', $headers)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $blocked->public_id);
    test()->getJson('/api/public/v1/vendors?external_system=sap_s4&external_id=9', $headers)->assertJsonCount(1, 'data');
    test()->getJson('/api/public/v1/vendors?linked=1', $headers)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $new->public_id);
    test()->getJson('/api/public/v1/vendors?linked=0', $headers)->assertJsonCount(2, 'data');
    test()->getJson('/api/public/v1/vendors?q=zeta', $headers)->assertJsonCount(1, 'data');
    test()->getJson('/api/public/v1/vendors?updated_since=yesterday-ish', $headers)->assertUnprocessable()->assertJsonValidationErrors(['updated_since']);
});

it('updates a vendor partially', function () {
    [$headers, $client] = integrationsVendorClient();
    $vendor = Vendor::factory()->create(['organization_id' => $client->organization_id]);

    test()->patchJson('/api/public/v1/vendors/'.$vendor->public_id, ['contact_name' => 'Khalid'], $headers)
        ->assertOk()
        ->assertJsonPath('data.contact_name', 'Khalid')
        ->assertJsonPath('data.name', $vendor->name);
});

it('never shows or changes another organization vendor', function () {
    [$headers] = integrationsVendorClient();
    $foreign = Vendor::factory()->create();

    test()->getJson('/api/public/v1/vendors/'.$foreign->public_id, $headers)->assertNotFound();
    test()->patchJson('/api/public/v1/vendors/'.$foreign->public_id, ['name' => 'x'], $headers)->assertNotFound();
    test()->getJson('/api/public/v1/vendors', $headers)->assertJsonCount(0, 'data');
});

it('needs vendors:read to list', function () {
    [$plain] = IntegrationsFixtures::apiKey(scopes: [ApiScope::OffersRead]);

    test()->getJson('/api/public/v1/vendors', IntegrationsFixtures::bearer($plain))
        ->assertForbidden()
        ->assertJsonPath('details.required_scope', 'vendors:read');
});
