<?php

declare(strict_types=1);

use App\Modules\Bidding\Broadcasting\IssuerLiveUpdated;
use App\Modules\Bidding\Broadcasting\OfferAcceptedBroadcast;
use App\Modules\Bidding\Broadcasting\ParticipantLiveUpdated;
use App\Modules\Competitions\Actions\CloseDueCompetition;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Enums\MustBeat;
use App\Modules\Competitions\Enums\RankVisibility;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Bidding\Scenario;

/*
 * The VisibilityProjector (ARCHITECTURE §7.9): the participant projection per rank visibility
 * and show_prices, the initial phase, the sealed format for both audiences, and leak tests that
 * serialise every participant-facing payload.
 */

/**
 * @param  array<string, mixed>  $rules
 */
function visibilityScenario(object $test, array $rules = [], ?Closure $state = null, int $bidders = 3): Scenario
{
    return Scenario::make($test, $state ?? fn (CompetitionFactory $f) => $f->withoutFinalWindow()->live(), $bidders, [
        'start_price_minor' => 10_000_000,
        'reserve_price_minor' => 8_123_400,
        'min_step_bps' => 50,
        'min_step_minor' => null,
        'must_beat' => MustBeat::Own,
        'auto_extend_enabled' => false,
        'auto_extend_window_seconds' => null,
        'auto_extend_by_seconds' => null,
        'auto_extend_max' => null,
        'hard_stop_at' => null,
        ...$rules,
    ]);
}

/**
 * Three offers: participant 1 leads with 9 123 400, then 0 with 9 234 500, then 2 with 9 345 600.
 */
function placeOffers(Scenario $s): void
{
    $s->offer(0, 9_234_500)->assertCreated();
    $s->offer(1, 9_123_400)->assertCreated();
    $s->offer(2, 9_345_600)->assertCreated();
}

/**
 * @return array<string, mixed>
 */
function liveAs(object $test, Scenario $s, User $user): array
{
    $s->actingAs($user);

    return $test->getJson($s->url('live'))->assertOk()->json('data');
}

describe('participant projection while live (phase open)', function () {
    it('shows rank, leading flag, leading amount and ladder only as the rules allow', function (RankVisibility $visibility, bool $showPrices, bool $flag, bool $rank, bool $amount, bool $ladder) {
        $s = visibilityScenario($this, ['rank_visibility' => $visibility, 'show_prices' => $showPrices]);
        placeOffers($s);

        $live = liveAs($this, $s, $s->bidder(0)); // participant 0 is second

        expect($live['my_offer']['amount_minor'])->toBe(9_234_500)
            ->and($live['start_price_minor'])->toBe(10_000_000)
            ->and($live['is_leading'])->toBe($flag ? false : null)
            ->and($live['rank'])->toBe($rank ? 2 : null)
            ->and($live['ranked_count'])->toBe($rank ? 3 : null)
            ->and($live['leading_amount_minor'])->toBe($amount ? 9_123_400 : null)
            ->and($live['required_next_amount_minor'])->toBe(9_234_500 - 46_200)
            ->and($live)->not->toHaveKey('reserve_price_minor');

        if ($ladder) {
            expect($live['ladder'])->toBe([
                ['alias_no' => $s->participant(1)->alias_no, 'amount_minor' => 9_123_400, 'is_me' => false],
                ['alias_no' => $s->participant(0)->alias_no, 'amount_minor' => 9_234_500, 'is_me' => true],
                ['alias_no' => $s->participant(2)->alias_no, 'amount_minor' => 9_345_600, 'is_me' => false],
            ]);
        } else {
            expect($live['ladder'])->toBeNull();
        }

        $leader = liveAs($this, $s, $s->bidder(1));
        expect($leader['is_leading'])->toBe($flag ? true : null);
    })->with([
        'none, no prices' => [RankVisibility::None, false, false, false, false, false],
        'none, prices' => [RankVisibility::None, true, false, false, true, false],
        'leading flag, no prices' => [RankVisibility::LeadingFlag, false, true, false, false, false],
        'leading flag, prices' => [RankVisibility::LeadingFlag, true, true, false, true, false],
        'full, no prices' => [RankVisibility::Full, false, true, true, false, false],
        'full, prices' => [RankVisibility::Full, true, true, true, true, true],
    ]);

    it('bases required_next on the leading offer when must_beat is best', function () {
        $s = visibilityScenario($this, ['must_beat' => MustBeat::Best, 'show_prices' => true, 'rank_visibility' => RankVisibility::Full]);
        $s->offer(0, 9_500_000)->assertCreated();

        $live = liveAs($this, $s, $s->bidder(1));

        expect($live['my_offer'])->toBeNull()
            ->and($live['required_next_amount_minor'])->toBe(9_452_500)
            ->and($live['accepting_offers'])->toBeTrue();
    });
});

describe('participant projection in the initial phase', function () {
    it('shows only the own offer, the start price and the own bound', function () {
        $s = visibilityScenario($this, ['rank_visibility' => RankVisibility::Full, 'show_prices' => true], fn (CompetitionFactory $f) => $f->live());
        placeOffers($s);

        $live = liveAs($this, $s, $s->bidder(0));

        expect($live['phase'])->toBe('initial')
            ->and($live['my_offer']['stage'])->toBe('initial')
            ->and($live['is_leading'])->toBeNull()
            ->and($live['rank'])->toBeNull()
            ->and($live['ranked_count'])->toBeNull()
            ->and($live['leading_amount_minor'])->toBeNull()
            ->and($live['ladder'])->toBeNull()
            ->and($live['required_next_amount_minor'])->toBe(9_234_500 - 46_200);
    });
});

describe('sealed format', function () {
    it('keeps offers invisible to other participants and amounts hidden from the issuer until the close', function () {
        $s = visibilityScenario($this, [], fn (CompetitionFactory $f) => $f->sealed()->live());
        $s->offer(0, 9_234_500)->assertCreated();
        $s->offer(1, 9_123_400)->assertCreated();

        $participant = liveAs($this, $s, $s->bidder(0));

        expect($participant['phase'])->toBe('sealed')
            ->and($participant['my_offer']['amount_minor'])->toBe(9_234_500)
            ->and($participant['is_leading'])->toBeNull()
            ->and($participant['rank'])->toBeNull()
            ->and($participant['leading_amount_minor'])->toBeNull()
            ->and($participant['ladder'])->toBeNull()
            ->and($participant['required_next_amount_minor'])->toBeNull();

        $issuer = liveAs($this, $s, $s->issuer);

        expect($issuer['leader'])->toBeNull()
            ->and($issuer['reserve_met'])->toBeNull()
            ->and($issuer['metrics']['improvement_vs_start_bps'])->toBeNull()
            ->and($issuer['metrics']['offers_count'])->toBe(2)
            ->and(collect($issuer['ranking'])->pluck('current_amount_minor')->filter()->all())->toBe([])
            ->and(collect($issuer['ranking'])->pluck('rank')->filter()->all())->toBe([])
            ->and(collect($issuer['ranking'])->pluck('is_leader')->filter()->all())->toBe([])
            ->and(collect($issuer['ranking'])->where('submitted', true)->count())->toBe(2)
            // Neutral order: by alias, never by rank.
            ->and(collect($issuer['ranking'])->pluck('alias_no')->all())->toBe(collect($issuer['ranking'])->pluck('alias_no')->sort()->values()->all());

        $s->actingAs($s->issuer);
        $rows = $this->getJson($s->url('offers'))->assertOk()->json('data');
        $log = $this->getJson($s->url('offers/log'))->assertOk()->json('data');

        expect(collect($rows)->pluck('current_amount_minor')->filter()->all())->toBe([])
            ->and(collect($rows)->pluck('change_ratio_bps')->filter()->all())->toBe([])
            ->and(collect($log)->pluck('amount_minor')->filter()->all())->toBe([])
            ->and(json_encode([$issuer, $rows, $log]))->not->toContain('9234500')->not->toContain('9123400');
    });

    it('unlocks the amounts for the issuer at the close, and only for the issuer', function () {
        Queue::fake();
        $s = visibilityScenario($this, [], fn (CompetitionFactory $f) => $f->sealed()->live());
        $s->offer(0, 9_234_500)->assertCreated();
        $s->offer(1, 9_123_400)->assertCreated();

        $this->travelTo($s->competition->effective_close_at->addSecond());
        app(CloseDueCompetition::class)->handle($s->competition->id);

        $competition = $s->refresh();
        expect($competition->status->value)->toBe('closed')->and($competition->offers_opened_at)->not->toBeNull();

        $issuer = liveAs($this, $s, $s->issuer);

        expect($issuer['leader']['amount_minor'])->toBe(9_123_400)
            ->and($issuer['leader']['participant_id'])->toBe($s->participant(1)->public_id)
            ->and($issuer['reserve_met'])->toBeFalse()
            ->and($issuer['ranking'][0]['rank'])->toBe(1);

        $participant = liveAs($this, $s, $s->bidder(0));

        expect($participant['status'])->toBe('closed')
            ->and($participant['accepting_offers'])->toBeFalse()
            ->and($participant['leading_amount_minor'])->toBeNull()
            ->and($participant['rank'])->toBeNull()
            ->and(json_encode($participant))->not->toContain('9123400');
    });
});

describe('leak tests', function () {
    it('never leaks prices, the reserve or identities to participants when show_prices is off', function () {
        Event::fake([IssuerLiveUpdated::class, ParticipantLiveUpdated::class, OfferAcceptedBroadcast::class]);
        $s = visibilityScenario($this, ['rank_visibility' => RankVisibility::Full, 'show_prices' => false]);

        $payloads = [];
        $payloads[] = $s->offer(0, 9_234_500)->assertCreated()->json();
        $payloads[] = $s->offer(1, 9_123_400)->assertCreated()->json();
        $payloads[] = $s->offer(2, 9_345_600)->assertCreated()->json();

        foreach ([0, 1, 2] as $i) {
            $s->actingAs($s->bidder($i));
            $payloads[$i + 10] = $this->getJson($s->url('live'))->assertOk()->json();
            $payloads[$i + 20] = $this->getJson($s->url('my-offers'))->assertOk()->json();
        }

        Event::assertDispatched(ParticipantLiveUpdated::class);

        $broadcasts = Event::dispatched(ParticipantLiveUpdated::class)->map(fn (array $args) => $args[0]);

        foreach ($broadcasts as $broadcast) {
            // Each channel carries its own organization's snapshot only.
            $index = collect($s->participants)->search(fn ($p) => $p->organization->public_id === $broadcast->organizationId);
            expect($index)->not->toBeFalse();
            $payloads["broadcast-{$broadcast->organizationId}-{$broadcast->snapshot['v']}"] = ['index' => $index, 'data' => $broadcast->broadcastWith()];
        }

        $amounts = [0 => '9234500', 1 => '9123400', 2 => '9345600'];

        foreach ($payloads as $key => $payload) {
            $owner = is_string($key) ? $payload['index'] : ($key >= 20 ? $key - 20 : ($key >= 10 ? $key - 10 : $key));
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

            expect($json)->not->toContain('8123400', "reserve leaked in {$key}")
                ->not->toContain('reserve');

            foreach ($amounts as $i => $amount) {
                if ($i !== $owner) {
                    expect($json)->not->toContain($amount, "amount of participant {$i} leaked to {$owner} in {$key}");
                }
            }

            foreach ($s->participants as $i => $participant) {
                if ($i !== $owner) {
                    expect($json)->not->toContain($participant->public_id)
                        ->not->toContain($participant->organization->public_id)
                        ->not->toContain($participant->organization->name);
                }
            }

            expect($json)->not->toContain($s->issuerOrganization->name);
        }
    });

    it('sends the issuer channel the issuer projection and the offer log entry', function () {
        Event::fake([IssuerLiveUpdated::class, ParticipantLiveUpdated::class, OfferAcceptedBroadcast::class]);
        $s = visibilityScenario($this);

        $s->offer(0, 9_234_500)->assertCreated();

        Event::assertDispatched(IssuerLiveUpdated::class, fn (IssuerLiveUpdated $e) => $e->competitionId === $s->competition->public_id
            && $e->broadcastAs() === 'live.updated'
            && $e->broadcastOn()[0]->name === 'private-competition.'.$s->competition->public_id
            && $e->snapshot['leader']['amount_minor'] === 9_234_500
            && $e->snapshot['v'] === 1);

        Event::assertDispatched(OfferAcceptedBroadcast::class, fn (OfferAcceptedBroadcast $e) => $e->broadcastAs() === 'offer.accepted'
            && $e->entry['seq'] === 1
            && $e->entry['amount_minor'] === 9_234_500
            && $e->entry['participant']['organization']['id'] === $s->participant(0)->organization->public_id);
    });

    it('hides sealed amounts in the realtime offer log entry', function () {
        Event::fake([IssuerLiveUpdated::class, ParticipantLiveUpdated::class, OfferAcceptedBroadcast::class]);
        $s = visibilityScenario($this, [], fn (CompetitionFactory $f) => $f->sealed()->live());

        $s->offer(0, 9_234_500)->assertCreated();

        Event::assertDispatched(OfferAcceptedBroadcast::class, fn (OfferAcceptedBroadcast $e) => $e->entry['amount_minor'] === null && $e->entry['stage'] === 'sealed');
        Event::assertDispatched(IssuerLiveUpdated::class, fn (IssuerLiveUpdated $e) => $e->snapshot['leader'] === null && ! str_contains((string) json_encode($e->snapshot), '9234500'));
    });
});

describe('issuer endpoints', function () {
    it('lists standings best first with participants without offers last', function () {
        $s = visibilityScenario($this, bidders: 4);
        placeOffers($s);
        $s->actingAs($s->issuer);

        $rows = $this->getJson($s->url('offers'))
            ->assertOk()
            ->assertJsonStructure(['data' => [[
                'participant' => ['id', 'alias_no', 'joined_at', 'organization' => ['id', 'name', 'logo_url', 'email', 'phone', 'cr_number'], 'coverage'],
                'current_amount_minor', 'first_amount_minor', 'offers_count', 'last_offer_at', 'rank', 'is_leader',
                'change_ratio_bps', 'submitted', 'bafo' => ['shortlisted', 'submitted', 'reference_amount_minor'],
            ]]])
            ->json('data');

        expect(collect($rows)->pluck('rank')->all())->toBe([1, 2, 3, null])
            ->and($rows[0]['participant']['id'])->toBe($s->participant(1)->public_id)
            ->and($rows[0]['is_leader'])->toBeTrue()
            ->and($rows[0]['participant']['coverage'])->toBe('own_plan')
            ->and($rows[3]['submitted'])->toBeFalse();
    });

    it('pages the offer log by sequence', function () {
        $s = visibilityScenario($this);
        placeOffers($s);
        $s->offer(0, 9_000_000)->assertCreated();
        $s->actingAs($s->issuer);

        $this->getJson($s->url('offers/log?limit=2'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.seq', 1)
            ->assertJsonPath('data.0.amount_minor', 9_234_500)
            ->assertJsonPath('data.0.channel', 'web')
            ->assertJsonPath('data.0.voided', false)
            ->assertJsonPath('meta.last_seq', 2)
            ->assertJsonPath('meta.has_more', true);

        $this->getJson($s->url('offers/log?after_seq=2&limit=5'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.seq', 4)
            ->assertJsonPath('meta.last_seq', 4)
            ->assertJsonPath('meta.has_more', false);

        $this->getJson($s->url('offers/log?after_seq=9'))->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.last_seq', 9);
        $this->getJson($s->url('offers/log?limit=501'))->assertUnprocessable()->assertJsonValidationErrors(['limit']);
    });

    it('keeps the issuer endpoints away from participants and strangers', function (string $path) {
        $s = visibilityScenario($this);

        $s->actingAs($s->bidder(0));
        $this->getJson($s->url($path))->assertForbidden()->assertJsonPath('code', 'forbidden');

        $s->actingAs(User::factory()->withMembership(Organization::factory()->create(), OrgRole::Owner)->create());
        $this->getJson($s->url($path))->assertNotFound();
    })->with(['offers', 'offers/log', 'report']);

    it('requires authentication on every bidding endpoint', function (string $method, string $path) {
        $competition = Competition::factory()->live()->create();

        $this->json($method, '/api/app/v1/competitions/'.$competition->public_id.'/'.$path)->assertUnauthorized();
    })->with([
        ['GET', 'live'], ['POST', 'live/heartbeat'], ['POST', 'offers'], ['GET', 'offers'], ['GET', 'offers/log'],
        ['GET', 'my-offers'], ['POST', 'bafo-round'], ['POST', 'award'], ['GET', 'award'], ['POST', 'award/revoke'], ['GET', 'report'],
    ]);
});

describe('my offers', function () {
    it('lists the own offers newest first and refuses the issuer', function () {
        $s = visibilityScenario($this);
        $s->offer(0, 9_500_000)->assertCreated();
        $s->offer(0, 9_400_000)->assertCreated();
        $s->offer(1, 9_300_000)->assertCreated();

        $s->actingAs($s->bidder(0));
        $this->getJson($s->url('my-offers'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.amount_minor', 9_400_000)
            ->assertJsonPath('data.0.seq', 2)
            ->assertJsonPath('data.1.amount_minor', 9_500_000)
            ->assertJsonStructure(['data' => [['id', 'seq', 'amount_minor', 'stage', 'accepted_at', 'voided']]]);

        $s->actingAs($s->issuer);
        $this->getJson($s->url('my-offers'))->assertForbidden()->assertJsonPath('code', 'not_a_participant');
    });
});
