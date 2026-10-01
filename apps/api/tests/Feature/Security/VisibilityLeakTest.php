<?php

declare(strict_types=1);

use App\Modules\Bidding\Actions\FinishBafoRound;
use App\Modules\Competitions\Actions\CloseDueCompetition;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\MustBeat;
use App\Modules\Competitions\Enums\RankVisibility;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Bidding\Scenario;

/*
 * Security review (docs/build/SECURITY_REVIEW.md) S-03: every participant-facing projection of a
 * standing follows the same rule as the VisibilityProjector (ARCHITECTURE §7.3, §7.9). During a
 * BAFO round the offers are sealed, so neither the live view nor the competition list may tell a
 * participant whether it is still leading.
 */

beforeEach(function () {
    Queue::fake();
});

/**
 * A live tender with full ranks and prices and a BAFO round: participant 0 offers 9 500 000,
 * participant 1 leads with 9 400 000; the competition closes and both are shortlisted.
 */
function securityBafoRound(object $test): Scenario
{
    $s = Scenario::make($test, fn (CompetitionFactory $f) => $f->withoutFinalWindow()->withBafoRound(60)->live(), 2, [
        'start_price_minor' => 10_000_000,
        'min_step_bps' => 50,
        'min_step_minor' => null,
        'must_beat' => MustBeat::Own,
        'rank_visibility' => RankVisibility::Full,
        'show_prices' => true,
    ]);

    $s->offer(0, 9_500_000)->assertCreated();
    $s->offer(1, 9_400_000)->assertCreated();

    $test->travelTo($s->competition->effective_close_at->addSecond());
    app(CloseDueCompetition::class)->handle($s->competition->id);

    $s->actingAs($s->issuer);
    $test->postJson($s->url('bafo-round'), [
        'participant_ids' => [$s->participant(0)->public_id, $s->participant(1)->public_id],
        'duration_minutes' => 30,
    ])->assertOk();

    expect($s->refresh()->status)->toBe(CompetitionStatus::BafoRound);

    return $s;
}

/**
 * `is_leading` of the competition in the participant list of bidder `$index`.
 */
function securityListedLeading(object $test, Scenario $s, int $index): mixed
{
    $s->actingAs($s->bidder($index));
    $items = $test->getJson('/api/app/v1/competitions?role=participant')->assertOk()->json('data');
    $item = collect($items)->firstWhere('id', $s->competition->public_id);

    expect($item)->not->toBeNull();

    return $item['is_leading'];
}

it('does not tell a participant in the list whether a sealed BAFO offer overtook it', function () {
    $s = securityBafoRound($this);

    // Before any BAFO offer: the list agrees with the live view (sealed: no leading flag).
    $s->actingAs($s->bidder(1));
    expect($this->getJson($s->url('live'))->json('data.is_leading'))->toBeNull()
        ->and(securityListedLeading($this, $s, 1))->toBeNull();

    // Participant 0 overtakes with a sealed BAFO offer.
    $s->offer(0, 9_300_000)->assertCreated();

    expect(securityListedLeading($this, $s, 1))->toBeNull()
        ->and(securityListedLeading($this, $s, 0))->toBeNull();
});

it('shows the leading flag in the list again once the round has ended', function () {
    $s = securityBafoRound($this);
    $s->offer(0, 9_300_000)->assertCreated();

    $this->travelTo($s->refresh()->bafoRound()->firstOrFail()->cutoff_at->addSecond());
    app(FinishBafoRound::class)->handle($s->competition->id);
    expect($s->refresh()->status)->toBe(CompetitionStatus::Closed);

    expect(securityListedLeading($this, $s, 0))->toBeTrue()
        ->and(securityListedLeading($this, $s, 1))->toBeFalse();
});
