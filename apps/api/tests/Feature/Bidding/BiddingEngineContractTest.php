<?php

declare(strict_types=1);

use App\Modules\Bidding\Broadcasting\IssuerLiveUpdated;
use App\Modules\Bidding\Broadcasting\ParticipantLiveUpdated;
use App\Modules\Bidding\Contracts\BiddingEngine;
use App\Modules\Bidding\Events\OffersUnsealed;
use App\Modules\Bidding\Listeners\BumpVersionAndBroadcast;
use App\Modules\Bidding\Models\CompetitionLiveState;
use App\Modules\Bidding\Services\BiddingEngineService;
use App\Modules\Bidding\Services\LiveStateManager;
use App\Modules\Competitions\Actions\CloseDueCompetition;
use App\Modules\Competitions\Data\Viewer;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\ExtensionKind;
use App\Modules\Competitions\Enums\ViewerRole;
use App\Modules\Competitions\Events\CompetitionClosed;
use App\Modules\Competitions\Events\CompetitionExtended;
use App\Modules\Competitions\Events\CompetitionStatusChanged;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionExtension;
use App\Support\Auth\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Bidding\Scenario;

/*
 * The BiddingEngine contract (ARCHITECTURE §3.6) and its integration with the Competitions
 * lifecycle: the close transaction, sealed unlock, and the version bump + broadcast of lifecycle
 * events (§7.10).
 */

it('is bound as a singleton', function () {
    expect(app(BiddingEngine::class))->toBeInstanceOf(BiddingEngineService::class)
        ->and(app(BiddingEngine::class))->toBe(app(BiddingEngine::class));
});

it('finalises live bidding inside the close transaction', function () {
    Queue::fake();
    Event::fake([OffersUnsealed::class, CompetitionClosed::class]);
    $s = Scenario::make($this, fn (CompetitionFactory $f) => $f->withoutFinalWindow()->live(), attributes: ['start_price_minor' => 10_000_000]);
    $s->offer(0, 9_600_000)->assertCreated();
    $s->offer(1, 9_500_000)->assertCreated();
    $version = CompetitionLiveState::query()->findOrFail($s->competition->id)->version;

    $this->travelTo($s->competition->effective_close_at);
    app(CloseDueCompetition::class)->handle($s->competition->id);

    $state = CompetitionLiveState::query()->findOrFail($s->competition->id);

    expect($s->refresh()->status)->toBe(CompetitionStatus::Closed)
        ->and($state->version)->toBe($version + 1)
        ->and($state->leader_participant_id)->toBe($s->participant(1)->id);

    Event::assertDispatched(CompetitionClosed::class);
    Event::assertNotDispatched(OffersUnsealed::class);

    // The close raced with nothing: a later offer is refused.
    $s->offer(0, 9_400_000)->assertStatus(409)->assertJsonPath('code', 'offer_not_accepting');
});

it('unseals a sealed competition at the close', function () {
    Queue::fake();
    Event::fake([OffersUnsealed::class]);
    $s = Scenario::make($this, fn (CompetitionFactory $f) => $f->sealed()->live(), attributes: ['start_price_minor' => 10_000_000]);
    $s->offer(0, 9_600_000)->assertCreated();

    expect(app(BiddingEngine::class)->issuerLeadingAmount($s->competition))->toBeNull();

    $this->travelTo($s->competition->effective_close_at->addSecond());
    app(CloseDueCompetition::class)->handle($s->competition->id);

    Event::assertDispatched(OffersUnsealed::class, fn (OffersUnsealed $e) => $e->competition->offers_opened_at !== null);

    expect(app(BiddingEngine::class)->issuerLeadingAmount($s->refresh()))->toBe(9_600_000);
});

it('projects the live snapshot for each viewer role', function () {
    $s = Scenario::make($this, attributes: ['start_price_minor' => 10_000_000]);
    $s->offer(0, 9_600_000)->assertCreated();
    $engine = app(BiddingEngine::class);
    $organizationId = $s->participant(0)->organization_id;

    $issuer = $engine->snapshotFor($s->competition, new Viewer(ViewerRole::Issuer, $s->issuerOrganization->id));
    $client = $engine->snapshotFor($s->competition, new Viewer(ViewerRole::ApiClient, $s->issuerOrganization->id));
    $participant = $engine->snapshotFor($s->competition, new Viewer(ViewerRole::Participant, $organizationId, participant: $s->participant(0)));
    $invitee = $engine->snapshotFor($s->competition, new Viewer(ViewerRole::Invitee, 999));

    expect($issuer)->toHaveKeys(['ranking', 'leader', 'metrics'])
        ->and($client)->toHaveKey('ranking')
        ->and($participant)->toHaveKeys(['my_offer', 'required_next_amount_minor'])
        ->and($participant)->not->toHaveKey('ranking')
        ->and($invitee)->toBeNull()
        ->and($engine->participantsWithOffersCount($s->competition))->toBe(1)
        ->and($engine->issuerLeadingAmount($s->competition))->toBe(9_600_000);

    $draft = Competition::factory()->create();
    expect($engine->snapshotFor($draft, new Viewer(ViewerRole::Issuer, $draft->organization_id)))->toBeNull()
        ->and($engine->issuerLeadingAmount($draft))->toBeNull()
        ->and($engine->participantsWithOffersCount($draft))->toBe(0);
});

it('bumps the version and broadcasts a status change to everyone', function () {
    Event::fake([IssuerLiveUpdated::class, ParticipantLiveUpdated::class]);
    $s = Scenario::make($this, bidders: 2);

    app(BumpVersionAndBroadcast::class)->handle(new CompetitionStatusChanged($s->competition, CompetitionStatus::Scheduled, CompetitionStatus::Live, Actor::system()));

    expect(CompetitionLiveState::query()->findOrFail($s->competition->id)->version)->toBe(1);

    Event::assertDispatched(IssuerLiveUpdated::class, fn (IssuerLiveUpdated $e) => $e->snapshot['v'] === 1 && $e->snapshot['last_change'] === ['kind' => 'status', 'reason' => null]);
    Event::assertDispatched(ParticipantLiveUpdated::class, 2);
});

it('broadcasts an extension with its kind', function () {
    Event::fake([IssuerLiveUpdated::class, ParticipantLiveUpdated::class]);
    $s = Scenario::make($this, bidders: 1);
    $extension = CompetitionExtension::query()->create([
        'competition_id' => $s->competition->id,
        'kind' => ExtensionKind::Manual,
        'previous_close_at' => $s->competition->effective_close_at,
        'new_close_at' => $s->competition->effective_close_at->addMinutes(10),
        'reason' => 'More time for questions.',
    ]);

    app(BumpVersionAndBroadcast::class)->handle(new CompetitionExtended($s->competition, $extension, Actor::system()));

    Event::assertDispatched(ParticipantLiveUpdated::class, fn (ParticipantLiveUpdated $e) => $e->snapshot['last_change'] === ['kind' => 'extension', 'reason' => 'manual']);
});

it('creates the live state lazily and bumps it atomically', function () {
    $s = Scenario::make($this, bidders: 1);
    $manager = app(LiveStateManager::class);

    expect(CompetitionLiveState::query()->find($s->competition->id))->toBeNull()
        ->and($manager->bumpVersion($s->competition->id))->toBe(1)
        ->and($manager->bumpVersion($s->competition->id))->toBe(2)
        ->and(DB::table('competition_live_states')->where('competition_id', $s->competition->id)->value('version'))->toBe(2);
});

it('computes the phase and acceptance from the server clock', function () {
    $s = Scenario::make($this, fn (CompetitionFactory $f) => $f->live(), attributes: ['start_price_minor' => 10_000_000]);
    $s->actingAs($s->bidder(0));

    $this->getJson($s->url('live'))->assertJsonPath('data.phase', 'initial');

    $this->travelTo(CarbonImmutable::instance($s->competition->final_window_starts_at));
    $this->getJson($s->url('live'))->assertJsonPath('data.phase', 'final_window')->assertJsonPath('data.accepting_offers', true);

    $this->travelTo($s->competition->effective_close_at);
    $this->getJson($s->url('live'))->assertJsonPath('data.accepting_offers', false);
});
