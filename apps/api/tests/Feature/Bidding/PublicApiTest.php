<?php

declare(strict_types=1);

use App\Modules\Bidding\Enums\ErpSyncStatus;
use App\Modules\Bidding\Events\AwardErpSynced;
use App\Modules\Bidding\Models\Award;
use App\Modules\Competitions\Actions\CloseDueCompetition;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Enums\MustBeat;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Database\Factories\ApiKeyFactory;
use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ApiKey;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\Vendor;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\Bidding\Scenario;

/*
 * Public v1 offers, results and awards (API.md §3.5, flows F4–F6): the issuer projection, tenancy
 * (another organization's object → 404), scopes, cursor listing and the ERP write-back.
 */

beforeEach(function () {
    Queue::fake();
});

/**
 * @param  list<ApiScope>  $scopes
 */
function apiKeyFor(Organization $organization, array $scopes = [ApiScope::OffersRead, ApiScope::AwardsRead, ApiScope::AwardsSync]): string
{
    $organization->forceFill(['api_enabled' => true])->save();
    $client = ApiClient::factory()->scopes($scopes)->create(['organization_id' => $organization->id]);
    $plain = ApiKeyFactory::makePlainKey((string) config('bafo.integrations.key_environment', 'test'));
    ApiKey::factory()->withPlainKey($plain)->create(['api_client_id' => $client->id]);

    return $plain;
}

function publicGet(object $test, string $key, string $path): TestResponse
{
    return $test->getJson('/api/public/v1/'.ltrim($path, '/'), ['Authorization' => "Bearer {$key}"]);
}

function publicPost(object $test, string $key, string $path, array $body, ?string $idempotencyKey = null): TestResponse
{
    return $test->postJson('/api/public/v1/'.ltrim($path, '/'), $body, [
        'Authorization' => "Bearer {$key}",
        'Idempotency-Key' => $idempotencyKey ?? (string) Str::ulid(),
    ]);
}

function publicScenario(object $test, ?Closure $state = null): Scenario
{
    $s = Scenario::make($test, $state ?? fn (CompetitionFactory $f) => $f->withoutFinalWindow()->live(), 2, [
        'start_price_minor' => 10_000_000,
        'min_step_bps' => 50,
        'min_step_minor' => null,
        'must_beat' => MustBeat::Own,
    ]);

    $s->offer(0, 9_600_000)->assertCreated();
    $s->offer(1, 9_500_000)->assertCreated();

    return $s;
}

function closePublic(object $test, Scenario $s): void
{
    $test->travelTo($s->competition->effective_close_at->addSecond());
    app(CloseDueCompetition::class)->handle($s->competition->id);
    $s->refresh();
}

function issuePublicAward(object $test, Scenario $s, ?User $issuer = null): Award
{
    $s->actingAs($issuer ?? $s->issuer);
    $test->postJson($s->url('award'), ['participant_id' => $s->participant(1)->public_id])->assertCreated();

    return Award::query()->where('competition_id', $s->competition->id)->latest('id')->firstOrFail();
}

describe('offers and results', function () {
    it('lists the standings with the vendor references', function () {
        $s = publicScenario($this);
        $key = apiKeyFor($s->issuerOrganization);

        $vendor = Vendor::factory()->create(['organization_id' => $s->issuerOrganization->id]);
        ExternalRef::factory()->create([
            'organization_id' => $s->issuerOrganization->id,
            'refable_type' => 'vendor',
            'refable_id' => $vendor->id,
            'system' => 'sap_s4',
            'type' => 'supplier',
            'value' => '100045',
        ]);
        $s->participant(1)->invitation->forceFill(['vendor_id' => $vendor->id])->save();

        $rows = publicGet($this, $key, 'competitions/'.$s->competition->public_id.'/offers')
            ->assertOk()
            ->assertJsonMissingPath('meta.server_time')
            ->json('data');

        expect($rows[0]['participant']['id'])->toBe($s->participant(1)->public_id)
            ->and($rows[0]['current_amount_minor'])->toBe(9_500_000)
            ->and($rows[0]['vendor']['id'])->toBe($vendor->public_id)
            ->and($rows[0]['vendor']['external_refs'][0])->toBe(['system' => 'sap_s4', 'type' => 'supplier', 'id' => '100045', 'number' => null, 'url' => null])
            ->and($rows[1]['vendor'])->toBeNull();
    });

    it('hides sealed amounts until the offers are opened', function () {
        $s = publicScenario($this, fn (CompetitionFactory $f) => $f->sealed()->live());
        $key = apiKeyFor($s->issuerOrganization);

        $rows = publicGet($this, $key, 'competitions/'.$s->competition->public_id.'/offers')->assertOk()->json('data');

        expect(collect($rows)->pluck('current_amount_minor')->filter()->all())->toBe([])
            ->and(collect($rows)->pluck('submitted')->all())->toBe([true, true]);
    });

    it('serves results only after the close', function () {
        $s = publicScenario($this);
        $key = apiKeyFor($s->issuerOrganization);
        $path = 'competitions/'.$s->competition->public_id.'/results';

        publicGet($this, $key, $path)->assertStatus(409)->assertJsonPath('code', 'results_not_available');

        closePublic($this, $s);

        publicGet($this, $key, $path)
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'competition_id', 'reference_no', 'status', 'direction', 'closed_at', 'offers_opened_at',
                'leader_amount_minor', 'reserve_met', 'improvement_vs_start_bps',
                'ranking' => [['participant_id', 'alias_no', 'organization' => ['id', 'name', 'cr_number', 'vat_number'],
                    'vendor', 'rank', 'current_amount_minor', 'first_amount_minor', 'offers_count', 'last_offer_at', 'is_leader']],
                'generated_at',
            ]])
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.leader_amount_minor', 9_500_000)
            ->assertJsonPath('data.improvement_vs_start_bps', 500)
            ->assertJsonPath('data.ranking.0.rank', 1);
    });

    it('isolates tenants and enforces the scope', function () {
        $s = publicScenario($this);
        $otherKey = apiKeyFor(Organization::factory()->create());
        $noScopeKey = apiKeyFor($s->issuerOrganization, [ApiScope::CompetitionsRead]);

        publicGet($this, $otherKey, 'competitions/'.$s->competition->public_id.'/offers')->assertNotFound();
        publicGet($this, $otherKey, 'competitions/nope/offers')->assertNotFound();

        publicGet($this, $noScopeKey, 'competitions/'.$s->competition->public_id.'/offers')
            ->assertForbidden()
            ->assertJsonPath('code', 'insufficient_scope');

        $this->getJson('/api/public/v1/competitions/'.$s->competition->public_id.'/offers')->assertUnauthorized();
    });
});

describe('awards', function () {
    it('shows an award with VAT, the winner, its vendor and the competition refs', function () {
        $s = publicScenario($this);
        closePublic($this, $s);
        $key = apiKeyFor($s->issuerOrganization);
        ExternalRef::factory()->create([
            'organization_id' => $s->issuerOrganization->id,
            'refable_type' => 'competition',
            'refable_id' => $s->competition->id,
            'system' => 'sap_s4',
            'type' => 'purchase_requisition',
            'value' => '10004567',
        ]);
        $award = issuePublicAward($this, $s);

        publicGet($this, $key, 'awards/'.$award->public_id)
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'id', 'object', 'status',
                'competition' => ['id', 'reference_no', 'title', 'direction', 'format', 'external_refs'],
                'winner' => ['participant_id', 'organization' => ['id', 'name', 'legal_name_ar', 'legal_name_en', 'cr_number', 'vat_number'], 'vendor'],
                'amount_minor', 'currency', 'price_basis', 'vat_rate_bp', 'vat_minor', 'amount_incl_vat_minor',
                'is_leading_offer', 'rank_at_award', 'reserve_met', 'justification', 'offer' => ['id', 'seq', 'accepted_at'],
                'awarded_at', 'awarded_by' => ['name', 'email'], 'revoked_at', 'revoke_reason',
                'erp_sync' => ['status', 'message', 'synced_at', 'refs'], 'ledger_head_hash', 'updated_at',
            ]])
            ->assertJsonPath('data.object', 'award')
            ->assertJsonPath('data.amount_minor', 9_500_000)
            ->assertJsonPath('data.vat_minor', 1_425_000)
            ->assertJsonPath('data.amount_incl_vat_minor', 10_925_000)
            ->assertJsonPath('data.erp_sync.status', 'pending')
            ->assertJsonPath('data.competition.external_refs.0.id', '10004567');
    });

    it('lists awards with a cursor and filters', function () {
        $first = publicScenario($this);
        closePublic($this, $first);
        $awardA = issuePublicAward($this, $first);

        $second = Scenario::make($this, fn (CompetitionFactory $f) => $f->withoutFinalWindow()->live(), 2, ['organization_id' => $first->issuerOrganization->id, 'start_price_minor' => 10_000_000]);
        $second->offer(1, 9_900_000)->assertCreated();
        closePublic($this, $second);
        $awardB = issuePublicAward($this, $second, $first->issuer);
        $second->actingAs($first->issuer);
        $this->postJson($second->url('award/revoke'), ['reason' => 'The supplier withdrew.'])->assertOk();

        $key = apiKeyFor($first->issuerOrganization);

        $page = publicGet($this, $key, 'awards?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.type', 'cursor')
            ->assertJsonPath('meta.pagination.has_more', true);

        $next = publicGet($this, $key, 'awards?per_page=1&cursor='.$page->json('meta.pagination.next_cursor'))->assertOk();

        expect([$page->json('data.0.id'), $next->json('data.0.id')])->toEqualCanonicalizing([$awardA->public_id, $awardB->public_id]);

        publicGet($this, $key, 'awards?status=revoked')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $awardB->public_id);
        publicGet($this, $key, 'awards?competition_id='.$first->competition->public_id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $awardA->public_id);
        publicGet($this, $key, 'awards?erp_sync_status=synced')->assertJsonCount(0, 'data');
        publicGet($this, $key, 'awards?updated_since=2999-01-01T00:00:00Z')->assertJsonCount(0, 'data');
        publicGet($this, $key, 'awards?status=lost')->assertUnprocessable()->assertJsonValidationErrors(['status']);
        publicGet($this, $key, 'awards?cursor=garbage')->assertUnprocessable()->assertJsonValidationErrors(['cursor']);
        publicGet($this, $key, 'awards?per_page=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.pagination.has_more', false)->assertJsonPath('meta.pagination.next_cursor', null);

        // Another organization sees none of them.
        publicGet($this, apiKeyFor(Organization::factory()->create()), 'awards')->assertOk()->assertJsonCount(0, 'data');
        publicGet($this, apiKeyFor(Organization::factory()->create()), 'awards/'.$awardA->public_id)->assertNotFound();
    });

    it('filters awards by the competition ERP reference', function () {
        $s = publicScenario($this);
        closePublic($this, $s);
        $award = issuePublicAward($this, $s);
        ExternalRef::factory()->create([
            'organization_id' => $s->issuerOrganization->id,
            'refable_type' => 'competition',
            'refable_id' => $s->competition->id,
            'system' => 'sap_s4',
            'type' => 'purchase_requisition',
            'value' => 'PR-1',
        ]);
        $key = apiKeyFor($s->issuerOrganization);

        publicGet($this, $key, 'awards?external_system=sap_s4&external_id=PR-1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $award->public_id);
        publicGet($this, $key, 'awards?external_system=sap_s4&external_id=PR-2')->assertJsonCount(0, 'data');
        publicGet($this, $key, 'awards?external_system=sap_s4')->assertUnprocessable()->assertJsonValidationErrors(['external_id']);
    });
});

describe('ERP write-back', function () {
    it('records the sync with the ERP references', function () {
        Event::fake([AwardErpSynced::class]);
        $s = publicScenario($this);
        closePublic($this, $s);
        $award = issuePublicAward($this, $s);
        $key = apiKeyFor($s->issuerOrganization);

        publicPost($this, $key, 'awards/'.$award->public_id.'/erp-sync', [
            'status' => 'synced',
            'message' => 'PO created.',
            'external_refs' => [['system' => 'sap_s4', 'type' => 'purchase_order', 'id' => '4500001234']],
        ], 'erp-sync-key-1')
            ->assertOk()
            ->assertJsonPath('data.erp_sync.status', 'synced')
            ->assertJsonPath('data.erp_sync.message', 'PO created.')
            ->assertJsonPath('data.erp_sync.refs.0', ['system' => 'sap_s4', 'type' => 'purchase_order', 'id' => '4500001234', 'number' => null, 'url' => null]);

        $award->refresh();

        expect($award->erp_sync_status)->toBe(ErpSyncStatus::Synced)
            ->and($award->erp_synced_at)->not->toBeNull()
            ->and(ExternalRef::query()->where('refable_type', 'award')->where('refable_id', $award->id)->count())->toBe(1);

        Event::assertDispatched(AwardErpSynced::class);

        // Replaying the same request returns the stored response.
        publicPost($this, $key, 'awards/'.$award->public_id.'/erp-sync', [
            'status' => 'synced',
            'message' => 'PO created.',
            'external_refs' => [['system' => 'sap_s4', 'type' => 'purchase_order', 'id' => '4500001234']],
        ], 'erp-sync-key-1')->assertOk()->assertHeader('Idempotent-Replayed', 'true');
    });

    it('refuses a revoked award and a reference used by another award', function () {
        $s = publicScenario($this);
        closePublic($this, $s);
        $award = issuePublicAward($this, $s);
        $key = apiKeyFor($s->issuerOrganization);

        publicPost($this, $key, 'awards/'.$award->public_id.'/erp-sync', [
            'status' => 'synced',
            'external_refs' => [['system' => 'sap_s4', 'type' => 'purchase_order', 'id' => 'PO-1']],
        ])->assertOk();

        $s->actingAs($s->issuer);
        $this->postJson($s->url('award/revoke'), ['reason' => 'The supplier withdrew.'])->assertOk();

        publicPost($this, $key, 'awards/'.$award->public_id.'/erp-sync', ['status' => 'failed'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'award_not_active');

        $s->actingAs($s->issuer);
        $this->postJson($s->url('award'), ['participant_id' => $s->participant(1)->public_id])->assertCreated();
        $second = Award::query()->where('status', 'issued')->sole();

        publicPost($this, $key, 'awards/'.$second->public_id.'/erp-sync', [
            'status' => 'synced',
            'external_refs' => [['system' => 'sap_s4', 'type' => 'purchase_order', 'id' => 'PO-1']],
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'external_ref_conflict')
            ->assertJsonPath('details.existing_id', $award->public_id);
    });

    it('validates the body and requires the awards:sync scope and an Idempotency-Key', function () {
        $s = publicScenario($this);
        closePublic($this, $s);
        $award = issuePublicAward($this, $s);
        $key = apiKeyFor($s->issuerOrganization);
        $path = 'awards/'.$award->public_id.'/erp-sync';

        publicPost($this, $key, $path, ['status' => 'done'])->assertUnprocessable()->assertJsonValidationErrors(['status']);
        publicPost($this, $key, $path, ['status' => 'synced', 'external_refs' => [['system' => 'SAP S4', 'type' => 'po', 'id' => '1']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['external_refs.0.system']);

        $this->postJson('/api/public/v1/'.$path, ['status' => 'synced'], ['Authorization' => "Bearer {$key}"])
            ->assertStatus(400)
            ->assertJsonPath('code', 'idempotency_key_required');

        publicPost($this, apiKeyFor($s->issuerOrganization, [ApiScope::AwardsRead]), $path, ['status' => 'synced'])
            ->assertForbidden()
            ->assertJsonPath('code', 'insufficient_scope');
    });
});

it('never exposes a competition of another organization through its id', function () {
    $s = publicScenario($this);
    closePublic($this, $s);
    $key = apiKeyFor(Organization::factory()->create());

    publicGet($this, $key, 'competitions/'.$s->competition->public_id.'/results')->assertNotFound();

    expect(Competition::query()->count())->toBe(1);
});
