<?php

declare(strict_types=1);

use App\Modules\Bidding\Events\OfferAccepted;
use App\Modules\Bidding\Models\CompetitionLiveState;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Enums\Direction;
use App\Modules\Competitions\Enums\MustBeat;
use App\Modules\Competitions\Enums\RankVisibility;
use App\Modules\Identity\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\Bidding\Scenario;

/*
 * The acceptance rules of ARCHITECTURE §7.1–§7.6: direction, start price, the stage bound (step
 * and must-beat for both directions), sealed revisions, the outlier guard, the ranking and its
 * tie-break, and the ledger rows the engine writes.
 */

/**
 * @param  array<string, mixed>  $rules
 */
function engineScenario(object $test, array $rules = [], ?Closure $state = null, int $bidders = 2): Scenario
{
    $direction = $rules['direction'] ?? Direction::Tender;

    return Scenario::make(
        $test,
        $state ?? fn (CompetitionFactory $f) => $f->withoutFinalWindow()->live(),
        $bidders,
        [
            'direction' => $direction,
            'start_price_minor' => 10_000_000,
            'min_step_bps' => 50,
            'min_step_minor' => null,
            'must_beat' => MustBeat::Own,
            'rank_visibility' => RankVisibility::LeadingFlag,
            'show_prices' => false,
            ...$rules,
        ],
    );
}

function standingOf(Scenario $s, int $index): ParticipantStanding
{
    return ParticipantStanding::query()->findOrFail($s->participant($index)->id);
}

describe('start price', function () {
    it('treats the tender start price as a ceiling', function () {
        $s = engineScenario($this);

        $s->offer(0, 10_000_100)->assertUnprocessable()
            ->assertJsonPath('code', 'offer_start_price')
            ->assertJsonPath('details.start_price_minor', 10_000_000);

        $s->offer(0, 10_000_000)->assertCreated();
    });

    it('treats the auction start price as the opening price', function () {
        $s = engineScenario($this, ['direction' => Direction::Auction, 'must_beat' => MustBeat::Best, 'show_prices' => true, 'start_price_minor' => 1_000_000, 'min_step_bps' => null, 'min_step_minor' => 50_000]);

        $s->offer(0, 999_900)->assertUnprocessable()->assertJsonPath('code', 'offer_start_price');
        $s->offer(0, 1_000_000)->assertCreated();
    });

    it('applies without a start price only the other rules', function () {
        $s = engineScenario($this, ['start_price_minor' => null]);

        $s->offer(0, 123_400)->assertCreated();
    });
});

describe('must beat own (tender)', function () {
    it('requires every revision to improve on the own offer by the rounded step', function () {
        $s = engineScenario($this);

        $s->offer(0, 9_500_000)->assertCreated();

        // step = ceilTo(ceil(9 500 000 × 50 / 10000), 100) = 47 500 → bound 9 452 500
        $s->offer(0, 9_452_600)->assertUnprocessable()
            ->assertJsonPath('code', 'offer_step_not_met')
            ->assertJsonPath('details.required_amount_minor', 9_452_500);

        // Next bound: ceil(9 452 500 × 50 / 10000) = 47 263 → 47 300 → 9 405 200.
        $s->offer(0, 9_452_500)->assertCreated()
            ->assertJsonPath('data.live.required_next_amount_minor', 9_405_200);
    });

    it('allows ties with another participant and ranks them by time', function () {
        $s = engineScenario($this);

        $s->offer(0, 9_500_000)->assertCreated();
        $this->travel(1)->seconds();
        $s->offer(1, 9_500_000)->assertCreated();

        expect(standingOf($s, 0)->rank)->toBe(1)
            ->and(standingOf($s, 0)->is_leader)->toBeTrue()
            ->and(standingOf($s, 1)->rank)->toBe(2)
            ->and(standingOf($s, 1)->is_leader)->toBeFalse();
    });

    it('breaks a tie at the same instant by the lower sequence number', function () {
        $this->freezeTime();
        $s = engineScenario($this);

        $s->offer(1, 9_500_000)->assertCreated();
        $s->offer(0, 9_500_000)->assertCreated();

        expect(standingOf($s, 1)->rank)->toBe(1)->and(standingOf($s, 0)->rank)->toBe(2);
    });

    it('lets a worse first offer of another participant in without a bound', function () {
        $s = engineScenario($this);

        $s->offer(0, 9_000_000)->assertCreated();
        $s->offer(1, 9_800_000)->assertCreated();

        expect(standingOf($s, 0)->is_leader)->toBeTrue()->and(standingOf($s, 1)->rank)->toBe(2);
    });
});

describe('must beat best', function () {
    it('requires a tender offer to beat the leading offer by the step', function () {
        $s = engineScenario($this, ['must_beat' => MustBeat::Best, 'show_prices' => true]);

        $s->offer(0, 9_500_000)->assertCreated();

        $s->offer(1, 9_460_000)->assertUnprocessable()
            ->assertJsonPath('code', 'offer_step_not_met')
            ->assertJsonPath('details.required_amount_minor', 9_452_500);

        $s->offer(1, 9_452_500)->assertCreated()->assertJsonPath('data.live.is_leading', true);

        // The former leader must now beat the new leading offer, not its own offer:
        // ceil(9 452 500 × 50 / 10000) = 47 263 → 47 300.
        $s->offer(0, 9_452_500)->assertUnprocessable()->assertJsonPath('details.required_amount_minor', 9_405_200);
        $s->offer(0, 9_405_200)->assertCreated();
    });

    it('requires an auction offer to beat the leading offer by the step', function () {
        $s = engineScenario($this, [
            'direction' => Direction::Auction,
            'must_beat' => MustBeat::Best,
            'show_prices' => true,
            'start_price_minor' => 1_000_000,
            'min_step_bps' => null,
            'min_step_minor' => 50_000,
        ]);

        $s->offer(0, 1_000_000)->assertCreated();

        $s->offer(1, 1_040_000)->assertUnprocessable()
            ->assertJsonPath('code', 'offer_step_not_met')
            ->assertJsonPath('details.required_amount_minor', 1_050_000);

        $s->offer(1, 1_050_000)->assertCreated();
        $s->offer(0, 1_100_000)->assertCreated()->assertJsonPath('data.live.is_leading', true);

        expect(standingOf($s, 0)->rank)->toBe(1)->and(standingOf($s, 1)->rank)->toBe(2);
    });

    it('asks the leader to improve on its own leading offer', function () {
        $s = engineScenario($this, ['must_beat' => MustBeat::Best, 'show_prices' => true]);

        $s->offer(0, 9_500_000)->assertCreated();
        $s->offer(0, 9_480_000)->assertUnprocessable()->assertJsonPath('details.required_amount_minor', 9_452_500);
    });
});

describe('must beat own (auction)', function () {
    it('ranks the highest offer first and bounds revisions upward', function () {
        $s = engineScenario($this, [
            'direction' => Direction::Auction,
            'start_price_minor' => 1_000_000,
            'min_step_bps' => null,
            'min_step_minor' => null,
        ]);

        $s->offer(0, 1_100_000)->assertCreated();
        $s->offer(1, 1_050_000)->assertCreated();

        expect(standingOf($s, 0)->is_leader)->toBeTrue()
            ->and(standingOf($s, 0)->current_rank_key)->toBe(-1_100_000);

        // Without a step rule the step is one granularity unit: own + 100.
        $s->offer(1, 1_050_000)->assertUnprocessable()->assertJsonPath('details.required_amount_minor', 1_050_100);
        $s->offer(1, 1_150_000)->assertCreated();

        expect(standingOf($s, 1)->is_leader)->toBeTrue()->and(standingOf($s, 0)->rank)->toBe(2);
    });
});

describe('stages', function () {
    it('forces must beat own during the initial phase', function () {
        $s = engineScenario($this, ['must_beat' => MustBeat::Best, 'show_prices' => true], fn (CompetitionFactory $f) => $f->live());

        $s->offer(0, 9_500_000)->assertCreated()->assertJsonPath('data.offer.stage', 'initial');
        // Under must_beat best this tie would need 9 452 500; in the initial phase only the own offer counts.
        $s->offer(1, 9_500_000)->assertCreated()->assertJsonPath('data.offer.stage', 'initial');
    });

    it('applies the full rules in the final pricing window', function () {
        $s = engineScenario($this, ['must_beat' => MustBeat::Best, 'show_prices' => true], fn (CompetitionFactory $f) => $f->inFinalWindow());

        $s->offer(0, 9_500_000)->assertCreated()->assertJsonPath('data.offer.stage', 'live');
        $s->offer(1, 9_500_000)->assertUnprocessable()->assertJsonPath('code', 'offer_step_not_met');
    });

    it('lets sealed revisions move in either direction within the start price', function () {
        $s = engineScenario($this, [], fn (CompetitionFactory $f) => $f->sealed()->live());

        $s->offer(0, 9_000_000)->assertCreated()->assertJsonPath('data.offer.stage', 'sealed');
        $s->offer(0, 9_500_000)->assertCreated();
        $s->offer(0, 9_400_000)->assertCreated();
        $s->offer(0, 10_000_100)->assertUnprocessable()->assertJsonPath('code', 'offer_start_price');

        expect(standingOf($s, 0)->offers_count)->toBe(3)
            ->and(standingOf($s, 0)->first_amount_minor)->toBe(9_000_000)
            ->and(standingOf($s, 0)->current_amount_minor)->toBe(9_400_000);
    });
});

describe('outlier guard', function () {
    it('asks to confirm a change above the threshold against the start price', function () {
        $s = engineScenario($this);

        $s->offer(0, 7_900_000)->assertUnprocessable()
            ->assertJsonPath('code', 'offer_outlier_confirm_required')
            ->assertJsonPath('details.change_bps', 2100)
            ->assertJsonPath('details.reference_amount_minor', 10_000_000);

        $s->offer(0, 7_900_000, extra: ['confirm_outlier' => true])->assertCreated();

        expect(Offer::query()->sole()->outlier_confirmed)->toBeTrue();
    });

    it('compares a revision with the own current offer, never with a hidden value', function () {
        $s = engineScenario($this, ['start_price_minor' => null]);

        $s->offer(0, 5_000_000)->assertCreated();
        $s->offer(1, 1_000_000)->assertCreated(); // another participant's amount is not a reference

        $s->offer(0, 3_900_000)->assertUnprocessable()
            ->assertJsonPath('details.reference_amount_minor', 5_000_000)
            ->assertJsonPath('details.change_bps', 2200);
    });

    it('does not guard a first offer without a start price', function () {
        $s = engineScenario($this, ['start_price_minor' => null]);

        $s->offer(0, 100)->assertCreated();
    });
});

describe('ranking and live state', function () {
    it('keeps standings, the leader and the reserve status in step with the ledger', function () {
        $s = engineScenario($this, ['reserve_price_minor' => 9_000_000], bidders: 3);

        $s->offer(0, 9_800_000)->assertCreated();
        $s->offer(1, 9_600_000)->assertCreated();
        $s->offer(2, 9_700_000)->assertCreated();

        $state = CompetitionLiveState::query()->findOrFail($s->competition->id);

        expect(standingOf($s, 1)->rank)->toBe(1)
            ->and(standingOf($s, 2)->rank)->toBe(2)
            ->and(standingOf($s, 0)->rank)->toBe(3)
            ->and($state->leader_participant_id)->toBe($s->participant(1)->id)
            ->and($state->leader_amount_minor)->toBe(9_600_000)
            ->and($state->reserve_met)->toBeFalse()
            ->and($state->accepted_offer_count)->toBe(3)
            ->and($state->participants_with_offers)->toBe(3)
            ->and($state->last_seq)->toBe(3)
            ->and($state->version)->toBe(3);

        $s->offer(0, 8_900_000, extra: ['confirm_outlier' => false])->assertCreated();

        $state->refresh();

        expect($state->leader_participant_id)->toBe($s->participant(0)->id)
            ->and($state->reserve_met)->toBeTrue()
            ->and(standingOf($s, 0)->first_amount_minor)->toBe(9_800_000)
            ->and(standingOf($s, 0)->offers_count)->toBe(2);
    });

    it('dispatches OfferAccepted with the change context', function () {
        Event::fake([OfferAccepted::class]);
        $s = engineScenario($this, ['rank_visibility' => RankVisibility::Full]);

        $s->offer(0, 9_800_000)->assertCreated();
        $s->offer(1, 9_600_000)->assertCreated();
        $s->offer(1, 9_500_000)->assertCreated();

        $events = Event::dispatched(OfferAccepted::class)->map(fn (array $args) => $args[0])->values();

        expect($events)->toHaveCount(3);

        [$first, $second, $third] = $events->all();

        expect($first->context->isFirstOfferOfParticipant)->toBeTrue()
            ->and($first->context->leaderChanged)->toBeTrue()
            ->and($first->context->previousLeaderParticipantId)->toBeNull()
            ->and($first->context->version)->toBe(1)
            ->and($second->context->leaderChanged)->toBeTrue()
            ->and($second->context->previousLeaderParticipantId)->toBe($s->participant(0)->id)
            ->and($second->context->changedParticipantIds)->toEqualCanonicalizing([$s->participant(0)->id, $s->participant(1)->id])
            ->and($second->context->leadingAmountChanged)->toBeTrue()
            ->and($third->context->isFirstOfferOfParticipant)->toBeFalse()
            ->and($third->context->leaderChanged)->toBeFalse()
            ->and($third->context->leadingAmountChanged)->toBeTrue()
            ->and($third->context->changedParticipantIds)->toBe([$s->participant(1)->id]);
    });
});

describe('ledger', function () {
    it('writes a gapless, hash-chained ledger with the direction rank key', function () {
        $s = engineScenario($this, bidders: 3);

        foreach ([[0, 9_900_000], [1, 9_800_000], [2, 9_850_000], [0, 9_700_000], [1, 9_600_000]] as [$index, $amount]) {
            $s->offer($index, $amount)->assertCreated();
        }

        $offers = Offer::query()->where('competition_id', $s->competition->id)->with('participant')->orderBy('seq')->get();
        $previous = null;

        foreach ($offers as $i => $offer) {
            // Read the stored timestamp back: the hash covers microseconds.
            $row = DB::selectOne("select to_char(accepted_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as t from offers where id = ?", [$offer->id]);
            $stored = $row->t;

            expect($offer->seq)->toBe($i + 1)
                ->and($offer->prev_hash)->toBe($previous)
                ->and($offer->rank_key)->toBe($offer->amount_minor)
                ->and($offer->created_at?->equalTo($offer->accepted_at))->toBeTrue()
                ->and($offer->hash)->toBe(hash('sha256', implode('|', [
                    $previous ?? '', $s->competition->public_id, (string) $offer->seq, $offer->participant->public_id,
                    (string) $offer->amount_minor, $offer->stage->value, $stored,
                ])));

            $previous = $offer->hash;
        }

        expect(CompetitionLiveState::query()->findOrFail($s->competition->id)->ledger_head_hash)->toBe($previous);
    });

    it('keeps ledgers of different competitions apart', function () {
        $a = engineScenario($this);
        $b = engineScenario($this);

        $a->offer(0, 9_900_000)->assertCreated();
        $b->offer(0, 9_900_000)->assertCreated()->assertJsonPath('data.offer.seq', 1);
        $a->offer(1, 9_800_000)->assertCreated()->assertJsonPath('data.offer.seq', 2);

        expect(Organization::query()->count())->toBeGreaterThan(4);
    });
});
