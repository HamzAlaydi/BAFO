<?php

declare(strict_types=1);

use App\Modules\Bidding\Broadcasting\Channels\BiddingChannels;
use App\Modules\Bidding\Broadcasting\IssuerLiveUpdated;
use App\Modules\Bidding\Broadcasting\OfferAcceptedBroadcast;
use App\Modules\Bidding\Broadcasting\ParticipantLiveUpdated;
use App\Modules\Bidding\Services\Heartbeats;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Enums\MustBeat;
use App\Modules\Competitions\Enums\RankVisibility;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Bidding\Scenario;

/*
 * GET …/live and POST …/live/heartbeat (API.md §1.6, ARCHITECTURE §9.4–§9.5), the channel
 * authorisation of §9.2 and the live-update recipients of §7.10.
 */

/**
 * @param  array<string, mixed>  $rules
 */
function liveScenario(object $test, array $rules = [], int $bidders = 3): Scenario
{
    return Scenario::make($test, fn (CompetitionFactory $f) => $f->withoutFinalWindow()->live(), $bidders, [
        'start_price_minor' => 10_000_000,
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

describe('GET live', function () {
    it('returns the issuer snapshot to the issuer, uncached', function () {
        $s = liveScenario($this);
        $s->offer(0, 9_500_000)->assertCreated();
        $s->actingAs($s->issuer);

        $response = $this->getJson($s->url('live'))
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'v', 'competition_id', 'direction', 'status', 'phase', 'server_time', 'bidding_opens_at',
                'effective_close_at', 'hard_stop_at', 'extension_count', 'leader', 'reserve_met',
                'ranking' => [['participant_id', 'alias_no', 'organization' => ['id', 'name', 'logo_url'],
                    'current_amount_minor', 'first_amount_minor', 'rank', 'is_leader', 'offers_count',
                    'last_offer_at', 'submitted', 'bafo' => ['shortlisted', 'submitted']]],
                'metrics' => ['offers_count', 'participants_joined', 'participants_with_offers', 'invitations_count', 'improvement_vs_start_bps'],
                'online_participants_count', 'bafo', 'last_change' => ['kind', 'reason'],
            ], 'meta' => ['server_time']])
            ->assertJsonPath('data.v', 1)
            ->assertJsonPath('data.metrics.improvement_vs_start_bps', 500)
            ->assertJsonPath('data.last_change.kind', 'snapshot');

        expect($response->headers->get('Cache-Control'))->toContain('no-store');
    });

    it('returns the participant snapshot to a participant', function () {
        $s = liveScenario($this);
        $s->actingAs($s->bidder(0));

        $this->getJson($s->url('live'))
            ->assertOk()
            ->assertJsonPath('data.v', 0)
            ->assertJsonPath('data.my_offer', null)
            ->assertJsonPath('data.accepting_offers', true)
            ->assertJsonMissingPath('data.ranking');
    });

    it('answers 403 not_a_participant to an invitee and 404 to a stranger', function () {
        $s = liveScenario($this);
        $organization = Organization::factory()->create();
        Invitation::factory()->forOrganization($organization)->create(['competition_id' => $s->competition->id, 'status' => InvitationStatus::Viewed]);

        $s->actingAs(User::factory()->withMembership($organization, OrgRole::Owner)->create());
        $this->getJson($s->url('live'))->assertForbidden()->assertJsonPath('code', 'not_a_participant');

        $s->actingAs(User::factory()->withMembership(Organization::factory()->create(), OrgRole::Owner)->create());
        $this->getJson($s->url('live'))->assertNotFound();
    });

    it('has no live view for a draft', function () {
        $issuerOrganization = Organization::factory()->create();
        $issuer = User::factory()->withMembership($issuerOrganization, OrgRole::Owner)->create();
        $draft = Competition::factory()->create(['organization_id' => $issuerOrganization->id]);

        Auth::forgetGuards();
        Sanctum::actingAs($issuer);

        $this->getJson('/api/app/v1/competitions/'.$draft->public_id.'/live')->assertNotFound();
    });

    it('is available once scheduled', function () {
        $s = Scenario::make($this, fn (CompetitionFactory $f) => $f->scheduled());
        $s->actingAs($s->bidder(0));

        $this->getJson($s->url('live'))
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.accepting_offers', false)
            ->assertJsonPath('data.phase', null);
    });
});

describe('heartbeat', function () {
    it('marks the participant online for the issuer', function () {
        $s = liveScenario($this);
        $s->actingAs($s->bidder(0));

        $this->postJson($s->url('live/heartbeat'))->assertNoContent();
        $s->actingAs($s->bidder(1));
        $this->postJson($s->url('live/heartbeat'))->assertNoContent();

        expect(app(Heartbeats::class)->isOnline($s->competition->id, $s->participant(0)->id))->toBeTrue();

        $s->actingAs($s->issuer);
        $this->getJson($s->url('live'))->assertJsonPath('data.online_participants_count', 2);

        $this->travel(46)->seconds();
        $this->getJson($s->url('live'))->assertJsonPath('data.online_participants_count', 0);
    });

    it('answers 204 to excess calls without refreshing the presence', function () {
        $s = liveScenario($this);
        $s->actingAs($s->bidder(0));

        $this->postJson($s->url('live/heartbeat'))->assertNoContent();
        $this->travel(40)->seconds();
        $this->postJson($s->url('live/heartbeat'))->assertNoContent(); // 40 s later: refreshes the presence
        $this->travel(5)->seconds();
        $this->postJson($s->url('live/heartbeat'))->assertNoContent(); // 5 s later: inside the 10 s interval, ignored
        $this->travel(41)->seconds();

        // The presence was refreshed 46 s ago (the ignored call did not extend it).
        expect(app(Heartbeats::class)->isOnline($s->competition->id, $s->participant(0)->id))->toBeFalse();
    });

    it('refuses the issuer and strangers', function () {
        $s = liveScenario($this);

        $s->actingAs($s->issuer);
        $this->postJson($s->url('live/heartbeat'))->assertForbidden()->assertJsonPath('code', 'not_a_participant');

        $s->actingAs(User::factory()->withMembership(Organization::factory()->create(), OrgRole::Owner)->create());
        $this->postJson($s->url('live/heartbeat'))->assertNotFound();
    });
});

describe('channel authorisation', function () {
    beforeEach(function () {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb' => [
                'driver' => 'reverb',
                'key' => 'bidding-key',
                'secret' => 'bidding-secret',
                'app_id' => 'bidding',
                'options' => ['host' => 'localhost', 'port' => 8085, 'scheme' => 'http', 'useTLS' => false],
                'client_options' => [],
            ],
        ]);
        app(BroadcastManager::class)->forgetDrivers();
        BiddingChannels::register();
    });

    function authorizeChannel(object $test, User $user, string $channel): TestResponse
    {
        Auth::forgetGuards();
        $token = $user->createToken('test')->plainTextToken;

        return $test->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => $channel], ['Authorization' => "Bearer {$token}"]);
    }

    it('lets the issuer team, and only it, listen on the issuer channel', function () {
        $s = liveScenario($this);
        $channel = 'private-competition.'.$s->competition->public_id;
        $teammate = User::factory()->withMembership($s->issuerOrganization, OrgRole::Member)->create();

        authorizeChannel($this, $s->issuer, $channel)->assertOk()->assertJsonStructure(['auth']);
        authorizeChannel($this, $teammate, $channel)->assertOk();
        authorizeChannel($this, $s->bidder(0), $channel)->assertForbidden();
    });

    it('lets a participant listen only on its own organization channel', function () {
        $s = liveScenario($this);
        $own = 'private-competition.'.$s->competition->public_id.'.participant.'.$s->participant(0)->organization->public_id;
        $other = 'private-competition.'.$s->competition->public_id.'.participant.'.$s->participant(1)->organization->public_id;

        authorizeChannel($this, $s->bidder(0), $own)->assertOk();
        authorizeChannel($this, $s->bidder(0), $other)->assertForbidden();
        authorizeChannel($this, $s->issuer, $own)->assertForbidden();
    });

    it('refuses an organization that has not joined', function () {
        $s = liveScenario($this);
        $organization = Organization::factory()->create();
        Invitation::factory()->forOrganization($organization)->create(['competition_id' => $s->competition->id, 'status' => InvitationStatus::Sent]);
        $user = User::factory()->withMembership($organization, OrgRole::Owner)->create();

        authorizeChannel($this, $user, 'private-competition.'.$s->competition->public_id.'.participant.'.$organization->public_id)->assertForbidden();
    });
});

describe('recipients of an offer update (§7.10)', function () {
    beforeEach(function () {
        Event::fake([IssuerLiveUpdated::class, ParticipantLiveUpdated::class, OfferAcceptedBroadcast::class]);
    });

    /**
     * @return list<string> organization public ids that received a participant snapshot for this version
     */
    function recipientsOf(int $version): array
    {
        return Event::dispatched(ParticipantLiveUpdated::class)
            ->map(fn (array $args) => $args[0])
            ->filter(fn (ParticipantLiveUpdated $e) => $e->snapshot['v'] === $version)
            ->map(fn (ParticipantLiveUpdated $e) => $e->organizationId)
            ->values()
            ->all();
    }

    function orgIds(Scenario $s, int ...$indexes): array
    {
        return array_map(fn (int $i) => $s->participant($i)->organization->public_id, $indexes);
    }

    it('tells the bidder and the previous and new leader with leading_flag', function () {
        $s = liveScenario($this, ['rank_visibility' => RankVisibility::LeadingFlag]);

        $s->offer(0, 9_500_000)->assertCreated();   // v1: 0 leads
        $s->offer(2, 9_900_000)->assertCreated();   // v2: no change of leader
        $s->offer(1, 9_400_000)->assertCreated();   // v3: 1 takes the lead from 0

        expect(recipientsOf(1))->toEqualCanonicalizing(orgIds($s, 0))
            ->and(recipientsOf(2))->toEqualCanonicalizing(orgIds($s, 2))
            ->and(recipientsOf(3))->toEqualCanonicalizing(orgIds($s, 1, 0));
    });

    it('tells every participant whose rank changed with full visibility', function () {
        $s = liveScenario($this, ['rank_visibility' => RankVisibility::Full]);

        $s->offer(0, 9_500_000)->assertCreated();   // v1
        $s->offer(1, 9_600_000)->assertCreated();   // v2: 1 ranked second
        $s->offer(2, 9_400_000)->assertCreated();   // v3: 2 first, 0 and 1 move down

        expect(recipientsOf(3))->toEqualCanonicalizing(orgIds($s, 0, 1, 2))
            ->and(recipientsOf(2))->toEqualCanonicalizing(orgIds($s, 1));
    });

    it('tells everyone when prices are shown and the leading amount changed', function () {
        $s = liveScenario($this, ['rank_visibility' => RankVisibility::None, 'show_prices' => true]);

        $s->offer(0, 9_500_000)->assertCreated();   // v1: leading amount set
        $s->offer(1, 9_600_000)->assertCreated();   // v2: leading amount unchanged

        expect(recipientsOf(1))->toEqualCanonicalizing(orgIds($s, 0, 1, 2))
            ->and(recipientsOf(2))->toEqualCanonicalizing(orgIds($s, 1));
    });

    it('tells only the bidder outside stage live', function () {
        $s = Scenario::make($this, fn (CompetitionFactory $f) => $f->sealed()->live(), 3, ['start_price_minor' => 10_000_000]);

        $s->offer(0, 9_500_000)->assertCreated();
        $s->offer(1, 9_400_000)->assertCreated();

        expect(recipientsOf(2))->toBe(orgIds($s, 1));
        Event::assertDispatched(IssuerLiveUpdated::class, 2);
    });
});
