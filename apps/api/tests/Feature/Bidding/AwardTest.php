<?php

declare(strict_types=1);

use App\Modules\Bidding\Enums\AwardStatus;
use App\Modules\Bidding\Enums\ErpSyncStatus;
use App\Modules\Bidding\Events\AwardIssued;
use App\Modules\Bidding\Events\AwardRevoked;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\CompetitionLiveState;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Actions\CloseDueCompetition;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\MustBeat;
use App\Modules\Competitions\Enums\ResultPublication;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLog;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Support\Bidding\Scenario;

/*
 * Award, revoke and re-award (ARCHITECTURE §7.12, T10/T11), the participant result and the
 * close-without-award outcome.
 */

beforeEach(function () {
    Queue::fake();
});

/**
 * Participant 1 leads with 9 400 000, participant 0 is second with 9 500 000, participant 2 has
 * no offer; the competition is closed.
 *
 * @param  array<string, mixed>  $rules
 */
function closedForAward(object $test, array $rules = []): Scenario
{
    $s = Scenario::make($test, fn (CompetitionFactory $f) => $f->withoutFinalWindow()->live(), 3, [
        'start_price_minor' => 10_000_000,
        'min_step_bps' => 50,
        'min_step_minor' => null,
        'must_beat' => MustBeat::Own,
        ...$rules,
    ]);

    $s->offer(0, 9_500_000)->assertCreated();
    $s->offer(1, 9_400_000)->assertCreated();

    $test->travelTo($s->competition->effective_close_at->addSecond());
    app(CloseDueCompetition::class)->handle($s->competition->id);
    $s->refresh();

    return $s;
}

function awardTo(object $test, Scenario $s, int $index, array $body = []): TestResponse
{
    $s->actingAs($s->issuer);

    return $test->postJson($s->url('award'), ['participant_id' => $s->participant($index)->public_id, ...$body]);
}

function justification(bool $requiresNote = false): CloseReason
{
    $factory = CloseReason::factory()->kind(CloseReasonKind::AwardJustification);

    return ($requiresNote ? $factory->requiresNote() : $factory)->create();
}

describe('issue', function () {
    it('awards the leading offer', function () {
        Event::fake([AwardIssued::class]);
        $s = closedForAward($this);
        $version = CompetitionLiveState::query()->findOrFail($s->competition->id)->version;

        $response = awardTo($this, $s, 1, ['message_to_winner' => 'Please send the contract.', 'internal_notes' => 'Best value.'])
            ->assertCreated()
            ->assertJsonStructure(['data' => [
                'id', 'status', 'participant' => ['id', 'alias_no', 'organization' => ['id', 'name', 'cr_number', 'vat_number']],
                'amount_minor', 'currency', 'price_basis', 'is_leading_offer', 'rank_at_award', 'reserve_met', 'justification',
                'message_to_winner', 'internal_notes', 'offer' => ['id', 'seq', 'accepted_at'], 'awarded_by' => ['id', 'name'],
                'awarded_at', 'revoked_at', 'revoke_reason', 'erp_sync' => ['status', 'message', 'synced_at', 'refs'],
                'ledger_head_hash', 'created_at',
            ]])
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.participant.id', $s->participant(1)->public_id)
            ->assertJsonPath('data.amount_minor', 9_400_000)
            ->assertJsonPath('data.currency', 'SAR')
            ->assertJsonPath('data.price_basis', 'excl_vat')
            ->assertJsonPath('data.is_leading_offer', true)
            ->assertJsonPath('data.rank_at_award', 1)
            ->assertJsonPath('data.reserve_met', null)
            ->assertJsonPath('data.justification', null)
            ->assertJsonPath('data.offer.seq', 2)
            ->assertJsonPath('data.awarded_by.id', $s->issuer->public_id)
            ->assertJsonPath('data.erp_sync.status', 'not_required');

        $state = CompetitionLiveState::query()->findOrFail($s->competition->id);
        $competition = $s->refresh();

        expect($response->json('data.ledger_head_hash'))->toBe($state->ledger_head_hash)
            ->and($state->version)->toBe($version + 1)
            ->and($competition->status)->toBe(CompetitionStatus::Awarded)
            ->and($competition->awarded_at)->not->toBeNull()
            ->and(AuditLog::query()->where('action', 'award.issued')->where('organization_id', $s->issuerOrganization->id)->exists())->toBeTrue();

        Event::assertDispatched(AwardIssued::class);
    });

    it('marks the ERP sync pending for an API-enabled issuer', function () {
        $s = closedForAward($this);
        $s->issuerOrganization->forceFill(['api_enabled' => true])->save();

        awardTo($this, $s, 1)->assertCreated()->assertJsonPath('data.erp_sync.status', 'pending');
    });

    it('requires a justification to award a non-leading offer', function () {
        $s = closedForAward($this);

        awardTo($this, $s, 0)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'award_justification_required')
            ->assertJsonPath('details.reason', 'not_leading');

        $reason = justification();

        awardTo($this, $s, 0, ['justification_reason_id' => $reason->public_id, 'justification_text' => 'Faster delivery.'])
            ->assertCreated()
            ->assertJsonPath('data.is_leading_offer', false)
            ->assertJsonPath('data.rank_at_award', 2)
            ->assertJsonPath('data.justification.reason.id', $reason->public_id)
            ->assertJsonPath('data.justification.reason.kind', 'award_justification')
            ->assertJsonPath('data.justification.text', 'Faster delivery.');
    });

    it('validates the justification reason and its note', function () {
        $s = closedForAward($this);

        awardTo($this, $s, 0, ['justification_reason_id' => CloseReason::factory()->kind(CloseReasonKind::Cancel)->create()->public_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['justification_reason_id']);

        awardTo($this, $s, 0, ['justification_reason_id' => justification(requiresNote: true)->public_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['justification_text']);

        awardTo($this, $s, 1, ['message_to_winner' => str_repeat('a', 2001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message_to_winner']);

        $s->actingAs($s->issuer);
        $this->postJson($s->url('award'), [])->assertUnprocessable()->assertJsonValidationErrors(['participant_id']);
    });

    it('requires a confirmation and a justification when the reserve is not met', function () {
        $s = closedForAward($this, ['reserve_price_minor' => 9_000_000]);

        awardTo($this, $s, 1)->assertUnprocessable()->assertJsonPath('code', 'award_reserve_confirmation_required');

        awardTo($this, $s, 1, ['confirm_reserve_not_met' => true])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'award_justification_required')
            ->assertJsonPath('details.reason', 'reserve_not_met');

        awardTo($this, $s, 1, ['confirm_reserve_not_met' => true, 'justification_reason_id' => justification()->public_id])
            ->assertCreated()
            ->assertJsonPath('data.reserve_met', false);
    });

    it('records a met reserve', function () {
        $s = closedForAward($this, ['reserve_price_minor' => 9_450_000]);

        awardTo($this, $s, 1)->assertCreated()->assertJsonPath('data.reserve_met', true);
    });

    it('refuses a participant without an offer and an unknown participant', function () {
        $s = closedForAward($this);

        awardTo($this, $s, 2)->assertUnprocessable()->assertJsonPath('code', 'award_participant_has_no_offer');

        $s->actingAs($s->issuer);
        $this->postJson($s->url('award'), ['participant_id' => '01j00000000000000000000000'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['participant_id']);
    });

    it('awards only a closed competition', function () {
        $s = Scenario::make($this, attributes: ['start_price_minor' => 10_000_000]);
        $s->offer(0, 9_500_000)->assertCreated();

        awardTo($this, $s, 0)
            ->assertStatus(409)
            ->assertJsonPath('code', 'invalid_state_transition')
            ->assertJsonPath('details.from', 'live')
            ->assertJsonPath('details.to', 'awarded');

        $closed = closedForAward($this);
        awardTo($this, $closed, 1)->assertCreated();
        awardTo($this, $closed, 1)->assertStatus(409)->assertJsonPath('details.from', 'awarded');
    });

    it('requires the competitions.award permission of the issuer', function () {
        $s = closedForAward($this);

        $s->actingAs(User::factory()->withMembership($s->issuerOrganization, OrgRole::Member)->create());
        $this->postJson($s->url('award'), ['participant_id' => $s->participant(1)->public_id])->assertForbidden();

        $s->actingAs($s->bidder(1));
        $this->postJson($s->url('award'), ['participant_id' => $s->participant(1)->public_id])->assertForbidden();

        $s->actingAs(User::factory()->withMembership(Organization::factory()->create(), OrgRole::Owner)->create());
        $this->postJson($s->url('award'), ['participant_id' => $s->participant(1)->public_id])->assertNotFound();

        expect(Award::query()->count())->toBe(0);
    });
});

describe('results', function () {
    it('tells each participant its outcome as the result publication allows', function (ResultPublication $publication, ?string $otherOutcome, ?int $winningAmount) {
        $s = closedForAward($this, ['result_publication' => $publication, 'show_prices' => $publication === ResultPublication::OutcomeAndAmount]);

        foreach ([1, 0] as $i) {
            $s->actingAs($s->bidder($i));
            $this->getJson($s->url('award'))->assertOk()->assertJsonPath('data', null);
        }

        awardTo($this, $s, 1, ['message_to_winner' => 'Welcome aboard.'])->assertCreated();

        $s->actingAs($s->bidder(1));
        $this->getJson($s->url('award'))
            ->assertOk()
            ->assertJsonPath('data.outcome', 'won')
            ->assertJsonPath('data.winning_amount_minor', $winningAmount)
            ->assertJsonPath('data.message_to_winner', 'Welcome aboard.');

        $s->actingAs($s->bidder(0));
        $other = $this->getJson($s->url('award'))
            ->assertOk()
            ->assertJsonPath('data.outcome', $otherOutcome)
            ->assertJsonPath('data.winning_amount_minor', $winningAmount)
            ->assertJsonMissingPath('data.message_to_winner');

        $live = $this->getJson($s->url('live'))->assertOk()->json('data');

        expect($live['result'])->toBe(['outcome' => $otherOutcome, 'winning_amount_minor' => $winningAmount])
            ->and(json_encode($other->json()))->not->toContain('Welcome aboard');

        $s->actingAs($s->issuer);
        $this->getJson($s->url('award'))->assertOk()->assertJsonPath('data.status', 'issued')->assertJsonPath('data.internal_notes', null);
    })->with([
        'none' => [ResultPublication::None, null, null],
        'outcome only' => [ResultPublication::OutcomeOnly, 'not_selected', null],
        'outcome and amount' => [ResultPublication::OutcomeAndAmount, 'not_selected', 9_400_000],
    ]);

    it('reports not_awarded after a close without award', function (ResultPublication $publication, ?string $outcome) {
        $s = closedForAward($this, ['result_publication' => $publication]);
        // Competitions' CloseWithoutAward (T12) moves the competition to not_awarded.
        $s->refresh()->forceFill(['status' => CompetitionStatus::NotAwarded, 'not_awarded_at' => now()])->save();

        $s->actingAs($s->bidder(0));
        $this->getJson($s->url('award'))->assertOk()->assertJsonPath('data.outcome', $outcome);
        $this->getJson($s->url('live'))->assertOk()->assertJsonPath('data.result.outcome', $outcome);

        awardTo($this, $s, 1)->assertStatus(409)->assertJsonPath('code', 'invalid_state_transition');
    })->with([
        'outcome only' => [ResultPublication::OutcomeOnly, 'not_awarded'],
        'none' => [ResultPublication::None, null],
    ]);

    it('answers 404 to a stranger', function () {
        $s = closedForAward($this);

        $s->actingAs(User::factory()->withMembership(Organization::factory()->create(), OrgRole::Owner)->create());
        $this->getJson($s->url('award'))->assertNotFound();
    });
});

describe('revoke and re-award', function () {
    it('revokes the award, returns to evaluation and allows a new award', function () {
        Event::fake([AwardRevoked::class]);
        $s = closedForAward($this);
        awardTo($this, $s, 1)->assertCreated();

        $s->actingAs($s->issuer);
        $this->postJson($s->url('award/revoke'), ['reason' => 'The supplier withdrew.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked')
            ->assertJsonPath('data.revoke_reason', 'The supplier withdrew.');

        $competition = $s->refresh();

        expect($competition->status)->toBe(CompetitionStatus::Closed)
            ->and($competition->awarded_at)->toBeNull()
            ->and(Award::query()->sole()->revoked_by_user_id)->toBe($s->issuer->id)
            ->and(AuditLog::query()->where('action', 'award.revoked')->exists())->toBeTrue();

        Event::assertDispatched(AwardRevoked::class);

        // Re-award: a new row; the revoked one stays.
        awardTo($this, $s, 0, ['justification_reason_id' => justification()->public_id])->assertCreated();

        expect(Award::query()->count())->toBe(2)
            ->and(Award::query()->where('status', AwardStatus::Issued->value)->sole()->participant_id)->toBe($s->participant(0)->id);

        $this->getJson($s->url('award'))->assertOk()->assertJsonPath('data.participant.id', $s->participant(0)->public_id);
    });

    it('revokes only an awarded competition, with a reason', function () {
        $s = closedForAward($this);
        $s->actingAs($s->issuer);

        $this->postJson($s->url('award/revoke'), ['reason' => 'Nothing to revoke.'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'invalid_state_transition');

        awardTo($this, $s, 1)->assertCreated();
        $this->postJson($s->url('award/revoke'), ['reason' => 'no'])->assertUnprocessable()->assertJsonValidationErrors(['reason']);

        $s->actingAs($s->bidder(1));
        $this->postJson($s->url('award/revoke'), ['reason' => 'I would like to revoke.'])->assertForbidden();

        expect(Award::query()->sole()->status)->toBe(AwardStatus::Issued)
            ->and(Award::query()->sole()->erp_sync_status)->toBe(ErpSyncStatus::NotRequired);
    });
});
