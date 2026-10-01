<?php

declare(strict_types=1);

use App\Modules\Bidding\Actions\FinishBafoRound;
use App\Modules\Bidding\Enums\BafoRoundStatus;
use App\Modules\Bidding\Events\BafoRoundEnded;
use App\Modules\Bidding\Events\BafoRoundStarted;
use App\Modules\Bidding\Jobs\EndBafoRound;
use App\Modules\Bidding\Listeners\EndCancelledBafoRound;
use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Bidding\Models\CompetitionLiveState;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Actions\CancelCompetition;
use App\Modules\Competitions\Actions\CloseDueCompetition;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\MustBeat;
use App\Modules\Competitions\Enums\RankVisibility;
use App\Modules\Competitions\Events\CompetitionCancelled;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Auth\Actor;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Support\Bidding\Scenario;

/*
 * The BAFO round (ARCHITECTURE §7.11, T7/T8): the issuer starts one round after close with a
 * manual shortlist; each shortlisted participant submits one offer, not worse than its last,
 * before the cutoff; the round ends at the cutoff and the competition is back in evaluation.
 */

beforeEach(function () {
    Queue::fake();
});

/**
 * Live tender with a BAFO round; participant 0 offers 9 500 000, participant 1 leads with
 * 9 400 000, participant 2 has no offer; then the competition closes.
 *
 * @param  array<string, mixed>  $rules
 */
function closedForBafo(object $test, array $rules = []): Scenario
{
    $s = Scenario::make($test, fn (CompetitionFactory $f) => $f->withoutFinalWindow()->withBafoRound(60)->live(), 3, [
        'start_price_minor' => 10_000_000,
        'min_step_bps' => 50,
        'min_step_minor' => null,
        'must_beat' => MustBeat::Own,
        'rank_visibility' => RankVisibility::Full,
        'show_prices' => true,
        ...$rules,
    ]);

    $s->offer(0, 9_500_000)->assertCreated();
    $s->offer(1, 9_400_000)->assertCreated();

    $test->travelTo($s->competition->effective_close_at->addSecond());
    app(CloseDueCompetition::class)->handle($s->competition->id);
    $s->refresh();

    expect($s->competition->status)->toBe(CompetitionStatus::Closed);

    return $s;
}

function startBafo(object $test, Scenario $s, array $participantIndexes = [0, 1], ?int $duration = 30): TestResponse
{
    $s->actingAs($s->issuer);

    return $test->postJson($s->url('bafo-round'), array_filter([
        'participant_ids' => array_map(fn (int $i) => $s->participant($i)->public_id, $participantIndexes),
        'duration_minutes' => $duration,
    ], fn ($v) => $v !== null));
}

describe('start', function () {
    it('starts a round for the shortlist and moves the competition to bafo_round', function () {
        Event::fake([BafoRoundStarted::class]);
        $s = closedForBafo($this);
        $versionBefore = CompetitionLiveState::query()->findOrFail($s->competition->id)->version;

        startBafo($this, $s)
            ->assertOk()
            ->assertJsonPath('data.id', $s->competition->public_id)
            ->assertJsonPath('data.status', 'bafo_round')
            ->assertJsonPath('data.viewer_role', 'issuer')
            ->assertJsonPath('data.bafo_round.status', 'running')
            ->assertJsonPath('data.bafo_round.shortlist_count', 2);

        $round = BafoRound::query()->sole();
        $s->refresh();

        expect($s->competition->status)->toBe(CompetitionStatus::BafoRound)
            ->and($round->status)->toBe(BafoRoundStatus::Running)
            ->and($round->shortlist_count)->toBe(2)
            ->and($round->started_by_user_id)->toBe($s->issuer->id)
            ->and($round->cutoff_at->equalTo($round->starts_at->addMinutes(30)))->toBeTrue()
            ->and(ParticipantStanding::query()->findOrFail($s->participant(0)->id)->bafo_shortlisted)->toBeTrue()
            ->and(ParticipantStanding::query()->findOrFail($s->participant(0)->id)->bafo_reference_amount_minor)->toBe(9_500_000)
            ->and(ParticipantStanding::query()->findOrFail($s->participant(1)->id)->bafo_reference_amount_minor)->toBe(9_400_000)
            ->and(CompetitionLiveState::query()->findOrFail($s->competition->id)->version)->toBe($versionBefore + 1)
            ->and(AuditLog::query()->where('action', 'bafo_round.started')->exists())->toBeTrue();

        Event::assertDispatched(BafoRoundStarted::class);
        Queue::assertPushed(EndBafoRound::class, fn (EndBafoRound $job) => $job->competitionId === $s->competition->id && $job->delay !== null);
    });

    it('defaults the duration to the competition setting', function () {
        $s = closedForBafo($this);

        startBafo($this, $s, duration: null)->assertOk();

        $round = BafoRound::query()->sole();
        expect($round->cutoff_at->equalTo($round->starts_at->addMinutes(60)))->toBeTrue();
    });

    it('requires the closed status', function () {
        $s = Scenario::make($this, fn (CompetitionFactory $f) => $f->withBafoRound()->live(), attributes: ['start_price_minor' => 10_000_000]);
        $s->offer(0, 9_500_000)->assertCreated();

        startBafo($this, $s, [0])
            ->assertStatus(409)
            ->assertJsonPath('code', 'invalid_state_transition')
            ->assertJsonPath('details.from', 'live')
            ->assertJsonPath('details.to', 'bafo_round');
    });

    it('requires a competition published with the BAFO round', function () {
        $s = closedForBafo($this, ['bafo_round_enabled' => false, 'bafo_duration_minutes' => null]);

        startBafo($this, $s)->assertStatus(409)->assertJsonPath('code', 'bafo_not_enabled');
    });

    it('allows one round per competition', function () {
        $s = closedForBafo($this);
        startBafo($this, $s)->assertOk();

        $this->travel(31)->minutes();
        app(FinishBafoRound::class)->handle($s->competition->id);

        startBafo($this, $s)->assertStatus(409)->assertJsonPath('code', 'bafo_already_used');
    });

    it('rejects ids that are not joined participants with a current offer', function () {
        $s = closedForBafo($this);
        $s->actingAs($s->issuer);

        $unknown = '01j00000000000000000000000';

        $this->postJson($s->url('bafo-round'), ['participant_ids' => [$s->participant(0)->public_id, $s->participant(2)->public_id, $unknown]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'bafo_shortlist_invalid')
            ->assertJsonPath('details.invalid_participant_ids', [$s->participant(2)->public_id, $unknown]);

        expect(BafoRound::query()->count())->toBe(0);
    });

    it('validates the request', function (array $body, string $field) {
        $s = closedForBafo($this);
        $s->actingAs($s->issuer);

        $this->postJson($s->url('bafo-round'), $body)->assertUnprocessable()->assertJsonValidationErrors([$field]);
    })->with([
        'missing ids' => [[], 'participant_ids'],
        'empty ids' => [['participant_ids' => []], 'participant_ids'],
        'duration too short' => [['participant_ids' => ['01j00000000000000000000000'], 'duration_minutes' => 10], 'duration_minutes'],
        'duration too long' => [['participant_ids' => ['01j00000000000000000000000'], 'duration_minutes' => 5000], 'duration_minutes'],
    ]);

    it('requires the competitions.award permission of the issuer', function () {
        $s = closedForBafo($this);
        $member = User::factory()->withMembership($s->issuerOrganization, OrgRole::Member)->create();
        $s->actingAs($member);

        $this->postJson($s->url('bafo-round'), ['participant_ids' => [$s->participant(0)->public_id]])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $s->actingAs($s->bidder(0));
        $this->postJson($s->url('bafo-round'), ['participant_ids' => [$s->participant(0)->public_id]])->assertForbidden();

        $s->actingAs(User::factory()->withMembership(Organization::factory()->create(), OrgRole::Owner)->create());
        $this->postJson($s->url('bafo-round'), ['participant_ids' => [$s->participant(0)->public_id]])->assertNotFound();
    });
});

describe('BAFO offers', function () {
    it('accepts one offer per shortlisted participant, not worse than its reference and without a step', function () {
        $s = closedForBafo($this);
        startBafo($this, $s)->assertOk();

        $s->offer(0, 9_500_100)->assertUnprocessable()
            ->assertJsonPath('code', 'offer_bafo_worse_than_reference')
            ->assertJsonPath('details.reference_amount_minor', 9_500_000);

        // Equal to the reference: allowed (no step in the BAFO stage).
        $s->offer(0, 9_500_000)->assertCreated()
            ->assertJsonPath('data.offer.stage', 'bafo')
            ->assertJsonPath('data.live.bafo.submitted', true)
            ->assertJsonPath('data.live.accepting_offers', false);

        $s->offer(0, 9_300_000)->assertStatus(409)->assertJsonPath('code', 'offer_bafo_already_submitted');

        $s->offer(1, 9_350_000)->assertCreated();

        expect(ParticipantStanding::query()->findOrFail($s->participant(0)->id)->bafo_offer_id)->not->toBeNull();
    });

    it('refuses participants outside the shortlist', function () {
        $s = closedForBafo($this);
        startBafo($this, $s, [1])->assertOk();

        $s->offer(0, 9_000_000)->assertForbidden()->assertJsonPath('code', 'offer_not_shortlisted');
        $s->offer(2, 9_000_000)->assertForbidden()->assertJsonPath('code', 'offer_not_shortlisted');
    });

    it('refuses offers at or after the cutoff', function () {
        $s = closedForBafo($this);
        startBafo($this, $s)->assertOk();
        $round = BafoRound::query()->sole();

        $this->travelTo($round->cutoff_at);

        $response = $s->offer(0, 9_400_000)->assertStatus(409)->assertJsonPath('code', 'offer_closed');
        expect($response->json('details.closed_at'))->toBe($round->cutoff_at->utc()->format('Y-m-d\TH:i:s.v\Z'));
    });

    it('shows each participant only its own BAFO status while the round runs', function () {
        $s = closedForBafo($this);
        startBafo($this, $s, [0])->assertOk();

        $s->actingAs($s->bidder(0));
        $shortlisted = $this->getJson($s->url('live'))->assertOk()->json('data');

        $s->actingAs($s->bidder(1));
        $other = $this->getJson($s->url('live'))->assertOk()->json('data');

        expect($shortlisted['bafo'])->toMatchArray(['shortlisted' => true, 'submitted' => false, 'reference_amount_minor' => 9_500_000])
            ->and($shortlisted['accepting_offers'])->toBeTrue()
            // Visibility as in `sealed` during the round (§7.3), even with full ranks and prices.
            ->and($shortlisted['rank'])->toBeNull()
            ->and($shortlisted['leading_amount_minor'])->toBeNull()
            ->and($shortlisted['ladder'])->toBeNull()
            ->and($other['bafo'])->toMatchArray(['shortlisted' => false, 'submitted' => false, 'reference_amount_minor' => null])
            ->and($other['accepting_offers'])->toBeFalse()
            ->and(json_encode($other))->not->toContain('9500000');

        $s->actingAs($s->issuer);
        $this->getJson($s->url('live'))
            ->assertJsonPath('data.bafo.status', 'running')
            ->assertJsonPath('data.bafo.shortlist_count', 1)
            ->assertJsonPath('data.bafo.submitted_count', 0);
    });
});

describe('end', function () {
    it('ends the round at the cutoff, re-ranks with the BAFO offers and returns to closed', function () {
        Event::fake([BafoRoundEnded::class]);
        $s = closedForBafo($this);
        startBafo($this, $s)->assertOk();

        // Participant 0 overtakes the leader with its best and final offer.
        $s->offer(0, 9_300_000)->assertCreated();

        $round = BafoRound::query()->sole();

        // Not due yet: nothing changes.
        expect(app(FinishBafoRound::class)->handle($s->competition->id)?->equalTo($round->cutoff_at))->toBeTrue()
            ->and($s->refresh()->status)->toBe(CompetitionStatus::BafoRound);

        $this->travelTo($round->cutoff_at);
        (new EndBafoRound($s->competition->id))->handle(app(FinishBafoRound::class));

        $round->refresh();
        $state = CompetitionLiveState::query()->findOrFail($s->competition->id);

        expect($round->status)->toBe(BafoRoundStatus::Ended)
            ->and($round->ended_at)->not->toBeNull()
            ->and($s->refresh()->status)->toBe(CompetitionStatus::Closed)
            ->and($state->leader_participant_id)->toBe($s->participant(0)->id)
            ->and($state->leader_amount_minor)->toBe(9_300_000)
            ->and(ParticipantStanding::query()->findOrFail($s->participant(0)->id)->rank)->toBe(1)
            ->and(AuditLog::query()->where('action', 'bafo_round.ended')->exists())->toBeTrue();

        Event::assertDispatched(BafoRoundEnded::class);

        // Idempotent: a second run is a no-op.
        expect(app(FinishBafoRound::class)->handle($s->competition->id))->toBeNull();
    });

    it('is driven by the bidding:tick safety net', function () {
        $s = closedForBafo($this);
        startBafo($this, $s)->assertOk();
        Queue::fake();

        $this->artisan('bidding:tick')->assertSuccessful();
        Queue::assertNotPushed(EndBafoRound::class);

        $this->travel(31)->minutes();
        $this->artisan('bidding:tick')->assertSuccessful();
        Queue::assertPushed(EndBafoRound::class, fn (EndBafoRound $job) => $job->competitionId === $s->competition->id);
    });

    it('ends the running round as soon as the competition is cancelled during it (T9)', function () {
        $s = closedForBafo($this);
        startBafo($this, $s)->assertOk();

        app(CancelCompetition::class)->handle($s->refresh(), CloseReason::factory()->create(), null, Actor::forUser($s->issuer));

        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === EndCancelledBafoRound::class);
        expect(BafoRound::query()->sole()->status)->toBe(BafoRoundStatus::Running);

        app(EndCancelledBafoRound::class)->handle(new CompetitionCancelled($s->refresh(), Actor::system()));

        expect(BafoRound::query()->sole())
            ->status->toBe(BafoRoundStatus::Ended)
            ->ended_at->not->toBeNull()
            ->and($s->refresh()->status)->toBe(CompetitionStatus::Cancelled);
    });

    it('closes a round quietly when the competition was cancelled during it', function () {
        $s = closedForBafo($this);
        startBafo($this, $s)->assertOk();
        $s->refresh()->forceFill(['status' => CompetitionStatus::Cancelled])->save();

        expect(app(FinishBafoRound::class)->handle($s->competition->id))->toBeNull()
            ->and(BafoRound::query()->sole()->status)->toBe(BafoRoundStatus::Ended)
            ->and($s->refresh()->status)->toBe(CompetitionStatus::Cancelled);
    });
});
