<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Region;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Enums\VendorSource;
use App\Modules\Integrations\Enums\VendorStatus;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\Vendor;
use App\Support\Audit\AuditLog;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Integrations\IntegrationsFixtures;

function integrationsVendorPayload(array $overrides = []): array
{
    return [
        'name' => 'شركة الريادة',
        'name_en' => 'Al Riyada Co.',
        'email' => 'Sales@Riyada.SA',
        'contact_name' => 'خالد',
        'phone' => '+966551234567',
        'cr_number' => '1010987654',
        'vat_number' => '300000000000013',
        'city' => 'الرياض',
        'notes' => 'مورد معتمد',
        ...$overrides,
    ];
}

it('adds a vendor with categories, region and ERP keys', function () {
    [$organization] = IntegrationsFixtures::signIn(OrgRole::Member);
    $region = Region::factory()->create(['code' => 'RIY']);
    $category = Category::factory()->create();

    $response = test()->postJson('/api/app/v1/vendors', integrationsVendorPayload([
        'region_id' => strtoupper($region->public_id),
        'category_ids' => [$category->public_id],
        'external_refs' => [['system' => 'sap_s4', 'type' => 'supplier', 'id' => '100045']],
    ]))
        ->assertCreated()
        ->assertJsonStructure(['data' => [
            'id', 'name', 'name_en', 'cr_number', 'vat_number', 'email', 'contact_name', 'phone', 'region' => ['id', 'code', 'name'],
            'city', 'categories' => [['id', 'code', 'name', 'is_other', 'auction_allowed']], 'status', 'linked_organization', 'source',
            'notes', 'external_refs' => [['system', 'type', 'id', 'number', 'url']], 'created_at', 'updated_at',
        ]]);

    $vendor = Vendor::query()->sole();

    expect($vendor->organization_id)->toBe($organization->id)
        ->and($vendor->email)->toBe('sales@riyada.sa')
        ->and($vendor->source)->toBe(VendorSource::Web)
        ->and($vendor->status)->toBe(VendorStatus::Active)
        ->and($response->json('data.region.code'))->toBe('RIY')
        ->and($response->json('data.external_refs'))->toBe([['system' => 'sap_s4', 'type' => 'supplier', 'id' => '100045', 'number' => null, 'url' => null]])
        ->and(AuditLog::query()->where('action', 'vendor.created')->count())->toBe(1);
});

it('links a vendor to the BAFO organization with the same e-mail or CR', function () {
    IntegrationsFixtures::signIn();
    $supplier = Organization::factory()->create(['cr_number' => '7001234567']);
    $member = User::factory()->withMembership(Organization::factory()->create())->create(['email' => 'buyer@other.sa']);

    test()->postJson('/api/app/v1/vendors', integrationsVendorPayload(['email' => 'x@y.sa', 'cr_number' => '7001234567']))
        ->assertCreated()
        ->assertJsonPath('data.linked_organization.id', $supplier->public_id)
        ->assertJsonStructure(['data' => ['linked_organization' => ['id', 'name', 'logo_url', 'verified']]]);

    test()->postJson('/api/app/v1/vendors', integrationsVendorPayload(['email' => 'BUYER@other.sa', 'cr_number' => null]))
        ->assertCreated()
        ->assertJsonPath('data.linked_organization.id', $member->membership->organization->public_id);
});

it('refuses a second vendor with the same e-mail', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $existing = Vendor::factory()->for($organization)->create(['email' => 'sales@riyada.sa']);

    test()->postJson('/api/app/v1/vendors', integrationsVendorPayload())
        ->assertStatus(409)
        ->assertJsonPath('code', 'vendor_email_taken')
        ->assertJsonPath('details.existing_id', $existing->public_id);
});

it('refuses an ERP key another vendor holds', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $holder = Vendor::factory()->for($organization)->create();
    ExternalRef::factory()->create([
        'organization_id' => $organization->id, 'refable_type' => 'vendor', 'refable_id' => $holder->id,
        'system' => 'sap_s4', 'type' => 'supplier', 'value' => '100045',
    ]);

    test()->postJson('/api/app/v1/vendors', integrationsVendorPayload([
        'external_refs' => [['system' => 'sap_s4', 'type' => 'supplier', 'id' => '100045']],
    ]))
        ->assertStatus(409)
        ->assertJsonPath('code', 'external_ref_conflict')
        ->assertJsonPath('details.existing_id', $holder->public_id);

    expect(Vendor::query()->count())->toBe(1);
});

it('validates the vendor fields', function () {
    IntegrationsFixtures::signIn();

    test()->postJson('/api/app/v1/vendors', [
        'email' => 'not-an-email',
        'phone' => '0551234567',
        'cr_number' => '12345',
        'vat_number' => '123456789012345',
        'region_id' => strtolower((string) Str::ulid()),
        'status' => 'archived',
        'external_refs' => [['system' => 'SAP S4', 'type' => 'supplier']],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['name', 'email', 'phone', 'cr_number', 'vat_number', 'region_id', 'status',
            'external_refs.0.system', 'external_refs.0.id']);
});

it('updates only the given fields and replaces the ERP keys', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $vendor = Vendor::factory()->for($organization)->create(['name' => 'Old']);
    ExternalRef::factory()->create([
        'organization_id' => $organization->id, 'refable_type' => 'vendor', 'refable_id' => $vendor->id,
        'system' => 'odoo', 'type' => 'supplier', 'value' => '1',
    ]);

    test()->patchJson('/api/app/v1/vendors/'.$vendor->public_id, [
        'name' => 'New',
        'status' => 'blocked',
        'external_refs' => [['system' => 'sap_s4', 'type' => 'supplier', 'id' => '2', 'number' => 'V-2']],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('data.status', 'blocked')
        ->assertJsonPath('data.email', $vendor->email)
        ->assertJsonPath('data.external_refs', [['system' => 'sap_s4', 'type' => 'supplier', 'id' => '2', 'number' => 'V-2', 'url' => null]]);

    expect(ExternalRef::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'vendor.updated')->sole()->changes)->toHaveKeys(['name', 'status']);
});

it('archives on delete and hides archived vendors by default', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $vendor = Vendor::factory()->for($organization)->create();
    Vendor::factory()->for($organization)->create();

    test()->deleteJson('/api/app/v1/vendors/'.$vendor->public_id)->assertOk()->assertJsonPath('data.status', 'archived');

    test()->getJson('/api/app/v1/vendors')->assertOk()->assertJsonCount(1, 'data');
    test()->getJson('/api/app/v1/vendors?status=archived')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $vendor->public_id);
});

it('lists and filters the directory with page pagination', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $category = Category::factory()->create();
    $region = Region::factory()->create();
    $match = Vendor::factory()->for($organization)->create(['name' => 'Alpha Trading', 'region_id' => $region->id]);
    $match->categories()->attach($category);
    Vendor::factory()->for($organization)->create(['name' => 'Beta']);
    Vendor::factory()->create(['name' => 'Alpha elsewhere']);

    test()->getJson('/api/app/v1/vendors?per_page=1')
        ->assertOk()
        ->assertJsonPath('meta.pagination.type', 'page')
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('meta.pagination.has_more', true);

    test()->getJson('/api/app/v1/vendors?q=alpha')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->public_id);
    test()->getJson('/api/app/v1/vendors?category_id='.$category->public_id)->assertJsonCount(1, 'data');
    test()->getJson('/api/app/v1/vendors?region_id='.$region->public_id)->assertJsonCount(1, 'data');
});

it('hides vendors of other organizations', function (string $method) {
    IntegrationsFixtures::signIn();
    $foreign = Vendor::factory()->create();

    test()->json($method, '/api/app/v1/vendors/'.$foreign->public_id, ['name' => 'x'])->assertNotFound();
})->with(['GET', 'PATCH', 'DELETE']);

it('requires competitions.create and a signed-in user', function () {
    test()->getJson('/api/app/v1/vendors')->assertUnauthorized();

    $organization = Organization::factory()->create();
    $user = User::factory()->withMembership($organization, OrgRole::Member)->create();
    $user->membership->forceFill(['status' => 'inactive'])->save();
    Sanctum::actingAs($user->refresh());

    test()->getJson('/api/app/v1/vendors')->assertForbidden();
});
