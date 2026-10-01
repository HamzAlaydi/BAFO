<?php

declare(strict_types=1);

use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\OfferRejection;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Bidding\Scenario;

/*
 * POST /competitions/{competition}/offers (API.md §1.6): the pre-checks and the order of
 * ARCHITECTURE §7.4, idempotency, the rate limit, the syntax checks and the state checks.
 */

function liveTender(object $test, array $attributes = [], int $bidders = 2): Scenario
{
    return Scenario::make($test, fn (CompetitionFactory $f) => $f->withoutFinalWindow()->live(), $bidders, [
        'start_price_minor' => 10_000_000,
        'min_step_bps' => 50,
        'min_step_minor' => null,
        ...$attributes,
    ]);
}

describe('happy path', function () {
    it('accepts an offer and returns it with the participant snapshot', function () {
        $s = liveTender($this);

        $response = $s->offer(0, 9_500_000)
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'offer' => ['id', 'seq', 'amount_minor', 'stage', 'accepted_at', 'voided'],
                    'live' => [
                        'v', 'competition_id', 'direction', 'status', 'phase', 'server_time', 'bidding_opens_at',
                        'effective_close_at', 'hard_stop_at', 'extension_count', 'accepting_offers', 'start_price_minor',
                        'min_step' => ['minor', 'bps'], 'amount_granularity_minor', 'my_offer', 'my_offers_count',
                        'is_leading', 'rank', 'ranked_count', 'leading_amount_minor', 'ladder',
                        'required_next_amount_minor', 'bafo', 'result', 'last_change' => ['kind', 'reason'],
                    ],
                ],
                'meta' => ['server_time'],
            ])
            ->assertJsonPath('data.offer.seq', 1)
            ->assertJsonPath('data.offer.amount_minor', 9_500_000)
            ->assertJsonPath('data.offer.stage', 'live')
            ->assertJsonPath('data.offer.voided', false)
            ->assertJsonPath('data.live.v', 1)
            ->assertJsonPath('data.live.my_offer.amount_minor', 9_500_000)
            ->assertJsonPath('data.live.my_offers_count', 1)
            ->assertJsonPath('data.live.required_next_amount_minor', 9_452_500)
            ->assertJsonPath('data.live.last_change.kind', 'offer');

        expect($response->json('data.offer.accepted_at'))->toBeIso8601Utc();

        $offer = Offer::query()->sole();

        expect($offer->public_id)->toBe($response->json('data.offer.id'))
            ->and($offer->organization_id)->toBe($s->participant(0)->organization_id)
            ->and($offer->submitted_by_user_id)->toBe($s->bidder(0)->id)
            ->and($offer->channel->value)->toBe('web')
            ->and($offer->prev_hash)->toBeNull();
    });

    it('records the channel from X-Platform', function () {
        $s = liveTender($this);
        $s->actingAs($s->bidder(0));

        $this->postJson($s->url('offers'), ['amount_minor' => 9_900_000], ['Idempotency-Key' => 'key-android-1', 'X-Platform' => 'android'])
            ->assertCreated();

        expect(Offer::query()->sole()->channel->value)->toBe('android');
    });

    it('accepts the amount as a string of digits', function () {
        $s = liveTender($this);

        $s->offer(0, '9900000')->assertCreated()->assertJsonPath('data.offer.amount_minor', 9_900_000);
    });
});

describe('pre-checks', function () {
    it('requires authentication', function () {
        $s = liveTender($this);

        $this->postJson($s->url('offers'), ['amount_minor' => 9_900_000], ['Idempotency-Key' => 'abcdefgh'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    });

    it('requires a well-formed Idempotency-Key', function (?string $key) {
        $s = liveTender($this);
        $s->actingAs($s->bidder(0));

        $headers = $key === null ? [] : ['Idempotency-Key' => $key];

        $this->postJson($s->url('offers'), ['amount_minor' => 9_900_000], $headers)
            ->assertStatus(400)
            ->assertJsonPath('code', 'idempotency_key_required');

        expect(Offer::query()->count())->toBe(0);
    })->with(['missing' => [null], 'too short' => ['abc'], 'bad characters' => ['key with spaces!']]);

    it('answers 404 for an unknown or malformed competition id', function (string $id) {
        $s = liveTender($this);
        $s->actingAs($s->bidder(0));

        $this->postJson("/api/app/v1/competitions/{$id}/offers", ['amount_minor' => 9_900_000], ['Idempotency-Key' => 'abcdefgh'])
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    })->with(['unknown' => ['01j00000000000000000000000'], 'malformed' => ['nope']]);

    it('answers 404 to an organization that cannot see the competition', function () {
        $s = liveTender($this);
        $stranger = User::factory()->withMembership(Organization::factory()->create(), OrgRole::Owner)->create();
        $s->actingAs($stranger);

        $this->postJson($s->url('offers'), ['amount_minor' => 9_900_000], ['Idempotency-Key' => 'abcdefgh'])
            ->assertNotFound();
    });

    it('answers 403 not_a_participant to the issuer and to an invitee', function () {
        $s = liveTender($this);
        $s->actingAs($s->issuer);

        $this->postJson($s->url('offers'), ['amount_minor' => 9_900_000], ['Idempotency-Key' => 'abcdefgh'])
            ->assertForbidden()
            ->assertJsonPath('code', 'not_a_participant');

        $inviteeOrganization = Organization::factory()->create();
        Invitation::factory()->forOrganization($inviteeOrganization)->create([
            'competition_id' => $s->competition->id,
            'status' => InvitationStatus::Sent,
        ]);
        $invitee = User::factory()->withMembership($inviteeOrganization, OrgRole::Owner)->create();
        $s->actingAs($invitee);

        $this->postJson($s->url('offers'), ['amount_minor' => 9_900_000], ['Idempotency-Key' => 'abcdefgh'])
            ->assertForbidden()
            ->assertJsonPath('code', 'not_a_participant');
    });

    it('rejects a malformed confirm_outlier flag as a validation error', function () {
        $s = liveTender($this);

        $s->offer(0, 9_900_000, extra: ['confirm_outlier' => 'maybe'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['confirm_outlier']);
    });
});

describe('idempotency', function () {
    it('replays the original offer for the same key and amount', function () {
        $s = liveTender($this);

        $first = $s->offer(0, 9_500_000, 'offer-key-0001')->assertCreated();

        $replay = $s->offer(0, 9_500_000, 'offer-key-0001')
            ->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.offer.id', $first->json('data.offer.id'))
            ->assertJsonPath('data.live.last_change.kind', 'snapshot');

        expect($replay->json('data.live.v'))->toBe(1)
            ->and(Offer::query()->count())->toBe(1);
    });

    it('rejects a reused key with another amount', function () {
        $s = liveTender($this);

        $s->offer(0, 9_500_000, 'offer-key-0002')->assertCreated();

        $s->offer(0, 9_400_000, 'offer-key-0002')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'idempotency_key_reused');

        expect(Offer::query()->count())->toBe(1);
    });

    it('replays a cached rejection with the same error and flags another amount as a reused key', function () {
        $s = liveTender($this);

        $s->offer(0, 10_500_000, 'offer-key-0003')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'offer_start_price');

        // The replay does not run the engine again: no second rejection row.
        $s->offer(0, 10_500_000, 'offer-key-0003')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'offer_start_price')
            ->assertJsonPath('details.start_price_minor', 10_000_000);

        $s->offer(0, 9_900_000, 'offer-key-0003')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'idempotency_key_reused');

        expect(OfferRejection::query()->count())->toBe(1);
    });

    it('scopes keys per participant', function () {
        $s = liveTender($this);

        $s->offer(0, 9_900_000, 'shared-key-01')->assertCreated();
        $s->offer(1, 9_800_000, 'shared-key-01')->assertCreated();

        expect(Offer::query()->count())->toBe(2);
    });
});

describe('rate limit', function () {
    it('accepts at most one offer per participant per interval', function () {
        $s = liveTender($this);

        $s->offer(0, 9_900_000)->assertCreated();

        $s->offer(0, 9_800_000, keepRateLimit: true)
            ->assertStatus(429)
            ->assertHeader('Retry-After', '2')
            ->assertJsonPath('code', 'too_many_requests')
            ->assertJsonPath('details.retry_after_seconds', 2);

        // Another participant is not affected.
        $s->offer(1, 9_800_000, keepRateLimit: true)->assertCreated();

        $this->travel(3)->seconds();

        $s->offer(0, 9_700_000, keepRateLimit: true)->assertCreated();
    });

    it('follows the bidding.offer_min_interval_seconds setting', function () {
        app(Settings::class)->set('bidding.offer_min_interval_seconds', 5, null);
        $s = liveTender($this);

        $s->offer(0, 9_900_000)->assertCreated();
        $s->offer(0, 9_800_000, keepRateLimit: true)->assertStatus(429)->assertHeader('Retry-After', '5');
    });
});

describe('syntax (§7.4 step 7)', function () {
    it('rejects amounts that are not positive integers', function (mixed $amount) {
        $s = liveTender($this);

        $s->offer(0, $amount)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'offer_amount_invalid');

        expect(OfferRejection::query()->count())->toBe(0);
    })->with([
        'zero' => [0],
        'negative' => [-100],
        'fraction' => [9_900_000.5],
        'text' => ['abc'],
        'missing' => [null],
    ]);

    it('rejects amounts above the platform maximum', function () {
        app(Settings::class)->set('bidding.max_amount_minor', 50_000_000, null);
        $s = liveTender($this, ['start_price_minor' => null]);

        $s->offer(0, 50_000_100)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'offer_amount_too_large')
            ->assertJsonPath('details.max_amount_minor', 50_000_000);
    });

    it('rejects amounts off the granularity', function () {
        $s = liveTender($this);

        $s->offer(0, 9_900_050)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'offer_granularity')
            ->assertJsonPath('details.granularity_minor', 100);
    });

    it('accepts halalas when the granularity is 1', function () {
        $s = liveTender($this, ['amount_granularity_minor' => 1]);

        $s->offer(0, 9_900_051)->assertCreated();
    });
});

describe('state (§7.4 step 10)', function () {
    it('rejects offers before bidding opens', function () {
        $s = liveTender($this, ['bidding_opens_at' => CarbonImmutable::now()->addMinutes(5)]);

        $response = $s->offer(0, 9_900_000)
            ->assertStatus(409)
            ->assertJsonPath('code', 'offer_not_accepting')
            ->assertJsonPath('details.status', 'live');

        expect($response->json('details.opens_at'))->toBeIso8601Utc();
    });

    it('rejects offers when the competition does not accept offers', function (Closure $state, string $status) {
        $s = Scenario::make($this, $state, attributes: ['start_price_minor' => 10_000_000]);

        $s->offer(0, 9_900_000)
            ->assertStatus(409)
            ->assertJsonPath('code', 'offer_not_accepting')
            ->assertJsonPath('details.status', $status);
    })->with([
        'scheduled' => [fn (CompetitionFactory $f) => $f->scheduled(), 'scheduled'],
        'closed' => [fn (CompetitionFactory $f) => $f->closed(), 'closed'],
        'awarded' => [fn (CompetitionFactory $f) => $f->awarded(), 'awarded'],
        'cancelled' => [fn (CompetitionFactory $f) => $f->cancelled(), 'cancelled'],
    ]);

    it('rejects an offer at or after effective_close_at even while the status is still live (the close race)', function (int $secondsAfterClose) {
        $s = liveTender($this);
        $closeAt = $s->competition->effective_close_at;

        $this->travelTo($closeAt->addSeconds($secondsAfterClose));

        $response = $s->offer(0, 9_900_000)
            ->assertStatus(409)
            ->assertJsonPath('code', 'offer_closed');

        expect($response->json('details.closed_at'))->toBe($closeAt->utc()->format('Y-m-d\TH:i:s.v\Z'))
            ->and($s->refresh()->status->value)->toBe('live')
            ->and(Offer::query()->count())->toBe(0);
    })->with(['exactly at the close' => [0], 'after the close' => [30]]);

    it('accepts an offer one microsecond before the close', function () {
        $s = liveTender($this);
        $this->travelTo($s->competition->effective_close_at->subMicrosecond());

        $s->offer(0, 9_900_000)->assertCreated();
    });

    it('writes a rejection row with the code, the stage and the DB time', function () {
        $s = liveTender($this);

        $s->offer(0, 10_000_100, 'rejected-key-1')->assertUnprocessable()->assertJsonPath('code', 'offer_start_price');

        $rejection = OfferRejection::query()->sole();

        expect($rejection->code)->toBe('offer_start_price')
            ->and($rejection->participant_id)->toBe($s->participant(0)->id)
            ->and($rejection->user_id)->toBe($s->bidder(0)->id)
            ->and($rejection->amount_minor)->toBe(10_000_100)
            ->and($rejection->stage)->toBe('live')
            ->and($rejection->idempotency_key)->toBe('rejected-key-1')
            ->and($rejection->db_time)->not->toBeNull()
            ->and($rejection->channel)->toBe('web');
    });

    it('does not show a deleted draft', function () {
        $s = liveTender($this);
        Competition::query()->whereKey($s->competition->id)->update(['status' => 'draft']);

        Auth::forgetGuards();
        Sanctum::actingAs($s->bidder(0));

        $this->postJson($s->url('offers'), ['amount_minor' => 9_900_000], ['Idempotency-Key' => 'abcdefgh'])
            ->assertNotFound();
    });
});
