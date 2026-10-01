<?php

declare(strict_types=1);

use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Catalog\Models\CompetitionPreset;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Enums\CompetitionSource;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\Vendor;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Competitions\Fixtures;

beforeEach(function (): void {
    Mail::fake();
    [$this->org, $this->owner] = Fixtures::issuer();
    $this->category = Category::factory()->create(['code' => 'it_hardware']);
    $this->region = Region::factory()->create(['code' => 'RIY']);
});

function allCompetitionScopes(): array
{
    return [ApiScope::CompetitionsRead, ApiScope::CompetitionsWrite, ApiScope::CompetitionsPublish, ApiScope::CompetitionsManage,
        ApiScope::InvitationsRead, ApiScope::InvitationsWrite];
}

it('creates a draft with codes, external refs and invitations (flow F2)', function (): void {
    $preset = CompetitionPreset::factory()->create(['code' => 'standard_live_tender']);
    $vendor = Vendor::factory()->create(['organization_id' => $this->org->id]);
    ExternalRef::query()->create(['organization_id' => $this->org->id, 'refable_type' => 'vendor', 'refable_id' => $vendor->id,
        'system' => 'sap_s4', 'type' => 'supplier', 'value' => '100045']);
    $headers = Fixtures::apiHeaders($this->org, allCompetitionScopes());
    $opens = now()->addDay()->startOfHour();

    $response = $this->postJson('/api/public/v1/competitions', [
        'title' => 'توريد أجهزة حاسب محمول 2026',
        'description' => 'نطاق العمل',
        'category_code' => 'it_hardware',
        'region_code' => 'RIY',
        'direction' => 'tender',
        'format' => 'live',
        'preset_code' => $preset->code,
        'rules' => ['start_price_minor' => 25_000_000, 'reserve_price_minor' => 21_000_000],
        'bidding_opens_at' => $opens->toIso8601String(),
        'scheduled_close_at' => $opens->addDays(7)->toIso8601String(),
        'external_refs' => [['system' => 'sap_s4', 'type' => 'purchase_requisition', 'id' => '10004567']],
        'invitations' => [
            ['vendor_external' => ['system' => 'sap_s4', 'id' => '100045'], 'sponsored' => false],
            ['email' => 'sales@newsupplier.sa', 'name' => 'Sales'],
        ],
    ], $headers);

    $response->assertCreated()
        ->assertHeader('Location')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.category.name', fn (array $name): bool => array_key_exists('ar', $name) && array_key_exists('en', $name))
        ->assertJsonPath('data.external_refs.0', ['system' => 'sap_s4', 'type' => 'purchase_requisition', 'id' => '10004567', 'number' => null, 'url' => null])
        ->assertJsonPath('data.created_by.type', 'api_client')
        ->assertJsonPath('data.rules.reserve_price_minor', 21_000_000);

    expect($response->json('data'))->not->toHaveKeys(['permissions', 'viewer_role', 'live'])
        ->and($response->json())->not->toHaveKey('meta.server_time');

    $competition = Competition::query()->where('public_id', $response->json('data.id'))->firstOrFail();

    expect($competition->source)->toBe(CompetitionSource::Api)
        ->and($competition->created_by_api_client_id)->not->toBeNull()
        ->and(Invitation::query()->where('competition_id', $competition->id)->count())->toBe(2)
        ->and(Invitation::query()->where('competition_id', $competition->id)->where('vendor_id', $vendor->id)->exists())->toBeTrue();
});

it('rejects unknown ERP vendors with the vendor_not_found item code and creates nothing', function (): void {
    $headers = Fixtures::apiHeaders($this->org, allCompetitionScopes());

    $this->postJson('/api/public/v1/competitions', [
        ...Fixtures::createBody($this->category, $this->region),
        'category_id' => null, 'region_id' => null, 'category_code' => 'it_hardware', 'region_code' => 'RIY',
        'invitations' => [['vendor_external' => ['system' => 'sap_s4', 'id' => 'missing']]],
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('details.item_codes', ['invitations.0.vendor_id' => 'vendor_not_found']);

    expect(Competition::query()->where('organization_id', $this->org->id)->exists())->toBeFalse();
});

it('lists the organization competitions with cursor pagination and filters', function (): void {
    $mine = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id]);
    Competition::factory()->live()->create(['organization_id' => $this->org->id]);
    Competition::factory()->live()->create();
    ExternalRef::query()->create(['organization_id' => $this->org->id, 'refable_type' => 'competition', 'refable_id' => $mine->id,
        'system' => 'sap_s4', 'type' => 'rfq', 'value' => 'RFQ-1']);
    $headers = Fixtures::apiHeaders($this->org, [ApiScope::CompetitionsRead]);

    $this->getJson('/api/public/v1/competitions', $headers)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.pagination.type', 'cursor');

    $this->getJson('/api/public/v1/competitions?status=scheduled', $headers)->assertJsonCount(1, 'data');
    $this->getJson('/api/public/v1/competitions?external_system=sap_s4&external_id=RFQ-1', $headers)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->public_id);
    $this->getJson('/api/public/v1/competitions?per_page=1', $headers)->assertJsonCount(1, 'data')->assertJsonPath('meta.pagination.has_more', true);
});

it('hides other organizations competitions (404) and enforces scopes', function (): void {
    $foreign = Competition::factory()->live()->create();
    $mine = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id]);
    $headers = Fixtures::apiHeaders($this->org, [ApiScope::CompetitionsRead]);

    $this->getJson("/api/public/v1/competitions/{$foreign->public_id}", $headers)->assertNotFound();
    $this->getJson("/api/public/v1/competitions/{$mine->public_id}", $headers)->assertOk()->assertJsonPath('data.id', $mine->public_id);
    $this->patchJson("/api/public/v1/competitions/{$mine->public_id}", ['title' => 'x'], $headers)
        ->assertForbidden()
        ->assertJsonPath('code', 'insufficient_scope');
});

it('requires a credential', function (): void {
    $this->getJson('/api/public/v1/competitions')->assertUnauthorized()->assertJsonPath('code', 'invalid_token');
});

it('publishes, extends, cancels and closes through the lifecycle endpoints', function (): void {
    $draft = Fixtures::draft($this->org, null);
    Fixtures::draftInvitations($draft, 2);
    $headers = Fixtures::apiHeaders($this->org, allCompetitionScopes());

    $this->postJson("/api/public/v1/competitions/{$draft->public_id}/publish", [], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'scheduled');

    $cancel = CloseReason::factory()->kind(CloseReasonKind::Cancel)->create(['code' => 'cancel_budget_withdrawn']);
    $this->postJson("/api/public/v1/competitions/{$draft->public_id}/cancel", ['close_reason_code' => 'cancel_budget_withdrawn'],
        [...$headers, 'Idempotency-Key' => 'cancel-key-1'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancellation.reason.name', fn (array $name): bool => isset($name['ar'], $name['en']));

    $live = Competition::factory()->live()->create(['organization_id' => $this->org->id]);
    $this->postJson("/api/public/v1/competitions/{$live->public_id}/extend", [
        'new_close_at' => $live->effective_close_at->addHour()->toIso8601String(),
        'reason' => 'تمديد بطلب النظام',
    ], [...$headers, 'Idempotency-Key' => 'extend-key-1'])->assertOk()->assertJsonPath('data.schedule.extension_count', 1);

    $closed = Competition::factory()->closed()->create(['organization_id' => $this->org->id]);
    CloseReason::factory()->kind(CloseReasonKind::NotAwarded)->create(['code' => 'not_awarded_prices_above_budget']);
    $this->postJson("/api/public/v1/competitions/{$closed->public_id}/close", ['close_reason_code' => 'not_awarded_prices_above_budget'],
        [...$headers, 'Idempotency-Key' => 'close-key-1'])->assertOk()->assertJsonPath('data.status', 'not_awarded');

    expect($cancel->refresh())->not->toBeNull();
});

it('requires the Idempotency-Key on acting POSTs', function (): void {
    $draft = Fixtures::draft($this->org, null);
    $headers = Fixtures::apiHeaders($this->org, allCompetitionScopes());
    unset($headers['Idempotency-Key']);

    $this->postJson("/api/public/v1/competitions/{$draft->public_id}/publish", [], $headers)
        ->assertStatus(400)
        ->assertJsonPath('code', 'idempotency_key_required');
});

it('updates by status and deletes drafts', function (): void {
    $draft = Fixtures::draft($this->org, null);
    $scheduled = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id]);
    $headers = Fixtures::apiHeaders($this->org, allCompetitionScopes());

    $this->patchJson("/api/public/v1/competitions/{$draft->public_id}", [
        'region_code' => 'RIY',
        'external_refs' => [['system' => 'sap_s4', 'type' => 'rfq', 'id' => 'RFQ-9']],
    ], $headers)->assertOk()->assertJsonPath('data.region.code', 'RIY')->assertJsonPath('data.external_refs.0.id', 'RFQ-9');

    $this->patchJson("/api/public/v1/competitions/{$scheduled->public_id}", ['direction' => 'auction'], $headers)
        ->assertStatus(409)->assertJsonPath('code', 'competition_not_editable');

    $this->deleteJson("/api/public/v1/competitions/{$scheduled->public_id}", [], $headers)->assertStatus(409);
    $this->deleteJson("/api/public/v1/competitions/{$draft->public_id}", [], $headers)->assertNoContent();
});

it('manages attachments and invitations (flow F3)', function (): void {
    $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id]);
    [$sent] = Fixtures::invitee($competition);
    $headers = Fixtures::apiHeaders($this->org, allCompetitionScopes());

    $this->getJson("/api/public/v1/competitions/{$competition->public_id}/invitations", $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure(['data' => [['id', 'email', 'name', 'status', 'organization', 'vendor', 'sponsored', 'pass_status',
            'participant', 'sent_at', 'joined_at', 'declined_at', 'revoked_at', 'expired_at', 'updated_at']]]);

    $this->postJson("/api/public/v1/competitions/{$competition->public_id}/invitations", ['invitations' => [['email' => 'erp@acme.sa']]], $headers)
        ->assertCreated()
        ->assertJsonPath('data.0.status', 'sent');

    $this->deleteJson("/api/public/v1/competitions/{$competition->public_id}/invitations/{$sent->public_id}", [], $headers)
        ->assertOk()
        ->assertJsonPath('status', null)
        ->assertJsonPath('data.status', 'revoked');

    $this->postJson("/api/public/v1/competitions/{$competition->public_id}/attachments", [
        'kind' => 'external_link', 'url' => 'https://erp.example.sa/rfq/1', 'title' => 'RFQ',
    ], [...$headers, 'Idempotency-Key' => 'attach-key-1'])->assertCreated();

    $this->getJson("/api/public/v1/competitions/{$competition->public_id}/attachments", $headers)->assertOk()->assertJsonCount(1, 'data');

    expect($sent->refresh()->status)->toBe(InvitationStatus::Revoked)
        ->and($competition->refresh()->status)->toBe(CompetitionStatus::Scheduled);
});
