<?php

declare(strict_types=1);

use App\Modules\Bidding\Enums\OfferStage;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\CompetitionLiveState;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Events\CompetitionCancelled;
use App\Modules\Competitions\Events\CompetitionClosed;
use App\Modules\Competitions\Events\CompetitionClosedWithoutAward;
use App\Modules\Competitions\Events\CompetitionExtended;
use App\Modules\Competitions\Events\CompetitionPublished;
use App\Modules\Competitions\Events\InvitationDeclined;
use App\Modules\Competitions\Events\InvitationJoined;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionExtension;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\IntegrationsServiceProvider;
use App\Modules\Integrations\Jobs\DeliverWebhook;
use App\Modules\Integrations\Jobs\DispatchWebhookEvent;
use App\Modules\Integrations\Listeners\WriteAwardWebhooks;
use App\Modules\Integrations\Listeners\WriteCompetitionWebhooks;
use App\Modules\Integrations\Listeners\WriteInvitationWebhooks;
use App\Modules\Integrations\Listeners\WriteOfferWebhooks;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\Vendor;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Models\WebhookEvent;
use App\Support\Auth\Actor;
use App\Support\Http\Iso;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

/**
 * An issuer with an endpoint subscribed to everything, and one of its competitions.
 *
 * @return array{0: Organization, 1: Competition}
 */
function integrationsIssuerCompetition(?Closure $state = null): array
{
    $issuer = Organization::factory()->apiEnabled()->create();
    WebhookEndpoint::factory()->for($issuer)->create();
    $factory = Competition::factory()->for($issuer);

    return [$issuer, ($state ?? fn ($f) => $f->scheduled())($factory)->create()];
}

function integrationsOutboxEvent(): WebhookEvent
{
    return WebhookEvent::query()->latest('id')->firstOrFail();
}

it('registers a synchronous listener for every webhook domain event by class name', function () {
    foreach (IntegrationsServiceProvider::WEBHOOK_LISTENERS as [$event, $listener, $method]) {
        expect(Event::getRawListeners()[$event] ?? [])->toContain([$listener, $method]);
        expect(method_exists($listener, $method))->toBeTrue();
    }

    expect(IntegrationsServiceProvider::WEBHOOK_LISTENERS)->toHaveCount(11);
});

// JSONB stores object keys in its own order: payload objects are compared with toEqual.
it('writes competition.published with the full envelope', function () {
    [$issuer, $competition] = integrationsIssuerCompetition();
    ExternalRef::factory()->create([
        'organization_id' => $issuer->id, 'refable_type' => 'competition', 'refable_id' => $competition->id,
        'system' => 'sap_s4', 'type' => 'purchase_requisition', 'value' => '10004567',
    ]);

    app(WriteCompetitionWebhooks::class)->published(new CompetitionPublished($competition, Actor::system()));

    $event = integrationsOutboxEvent();

    expect($event->type)->toBe('competition.published')
        ->and($event->organization_id)->toBe($issuer->id)
        ->and($event->subject_type)->toBe('competition')
        ->and($event->subject_id)->toBe($competition->id)
        ->and($event->sequence)->toBe(1)
        ->and($event->dispatched_at)->toBeNull()
        ->and(array_keys($event->payload))->toEqualCanonicalizing(['id', 'type', 'api_version', 'environment', 'occurred_at', 'organization_id', 'sequence', 'data', 'links'])
        ->and($event->payload['id'])->toBe($event->public_id)
        ->and($event->payload['api_version'])->toBe('v1')
        ->and($event->payload['environment'])->toBe('test')
        ->and($event->payload['organization_id'])->toBe($issuer->public_id)
        ->and($event->payload['occurred_at'])->toBeIso8601Utc()
        ->and($event->payload['links']['object'])->toBe(config('app.url').'/api/public/v1/competitions/'.$competition->public_id)
        ->and($event->payload['data']['object'])->toEqual([
            'id' => $competition->public_id,
            'object' => 'competition',
            'reference_no' => $competition->reference_no,
            'status' => 'scheduled',
            'direction' => $competition->direction->value,
            'format' => $competition->format->value,
            'bidding_opens_at' => Iso::format($competition->bidding_opens_at),
            'scheduled_close_at' => Iso::format($competition->scheduled_close_at),
            'external_refs' => [['system' => 'sap_s4', 'type' => 'purchase_requisition', 'id' => '10004567', 'number' => null, 'url' => null]],
        ]);

    Queue::assertPushedOn('webhooks', DispatchWebhookEvent::class, fn (DispatchWebhookEvent $job) => $job->webhookEventId === $event->id);
});

it('writes nothing when no active endpoint listens to the type', function (Closure $endpoint) {
    $issuer = Organization::factory()->apiEnabled()->create();
    $endpoint($issuer);
    $competition = Competition::factory()->for($issuer)->scheduled()->create();

    app(WriteCompetitionWebhooks::class)->published(new CompetitionPublished($competition, Actor::system()));

    expect(WebhookEvent::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'no endpoint' => [fn (Organization $issuer) => null],
    'other types only' => [fn (Organization $issuer) => WebhookEndpoint::factory()->for($issuer)->eventTypes(['award.issued'])->create()],
    'disabled endpoint' => [fn (Organization $issuer) => WebhookEndpoint::factory()->for($issuer)->disabled()->create()],
    'another organization endpoint' => [fn (Organization $issuer) => WebhookEndpoint::factory()->create()],
]);

it('numbers events per object', function () {
    [, $competition] = integrationsIssuerCompetition(fn ($f) => $f->live());
    $extension = CompetitionExtension::factory()->for($competition)->create();
    $listener = app(WriteCompetitionWebhooks::class);

    $listener->published(new CompetitionPublished($competition, Actor::system()));
    $listener->extended(new CompetitionExtended($competition, $extension, Actor::system()));

    [$first, $second] = WebhookEvent::query()->orderBy('id')->get()->all();

    expect([$first->sequence, $second->sequence])->toBe([1, 2])
        ->and($second->payload['sequence'])->toBe(2)
        ->and($second->payload['data']['object'])->toEqual([
            'id' => $competition->public_id,
            'object' => 'competition',
            'previous_close_at' => Iso::format($extension->previous_close_at),
            'effective_close_at' => Iso::format($competition->effective_close_at),
            'extension_count' => $competition->extension_count,
            'kind' => 'manual',
        ]);
});

it('writes competition.closed with the live counters and competition.offers_opened', function () {
    [, $competition] = integrationsIssuerCompetition(fn ($f) => $f->sealed()->closed());
    CompetitionLiveState::factory()->create(['competition_id' => $competition->id, 'participants_with_offers' => 3, 'accepted_offer_count' => 7]);
    $listener = app(WriteCompetitionWebhooks::class);

    $listener->closed(new CompetitionClosed($competition));

    expect(integrationsOutboxEvent()->payload['data']['object'])->toEqual([
        'id' => $competition->public_id, 'object' => 'competition', 'status' => 'closed',
        'closed_at' => Iso::format($competition->closed_at), 'participants_with_offers' => 3, 'offers_count' => 7,
    ]);

    $listener->offersOpened(new class($competition)
    {
        public function __construct(public Competition $competition) {}
    });

    expect(integrationsOutboxEvent()->type)->toBe('competition.offers_opened')
        ->and(integrationsOutboxEvent()->payload['data']['object'])->toHaveKeys(['id', 'object', 'offers_opened_at']);
});

it('writes competition.cancelled and competition.not_awarded with the reason', function () {
    $reason = CloseReason::factory()->kind(CloseReasonKind::Cancel)->create(['code' => 'cancel_budget_withdrawn']);
    [, $competition] = integrationsIssuerCompetition(fn ($f) => $f->cancelled());
    $competition->forceFill(['cancel_reason_id' => $reason->id, 'cancel_note' => 'budget'])->save();

    app(WriteCompetitionWebhooks::class)->cancelled(new CompetitionCancelled($competition, Actor::system()));

    expect(integrationsOutboxEvent()->payload['data']['object'])->toMatchArray([
        'object' => 'competition',
        'cancelled_at' => Iso::format($competition->cancelled_at),
        'reason' => ['code' => 'cancel_budget_withdrawn', 'name' => $reason->name],
        'note' => 'budget',
    ]);

    [, $closed] = integrationsIssuerCompetition(fn ($f) => $f->notAwarded());
    app(WriteCompetitionWebhooks::class)->notAwarded(new CompetitionClosedWithoutAward($closed, Actor::system()));

    expect(integrationsOutboxEvent()->type)->toBe('competition.not_awarded')
        ->and(integrationsOutboxEvent()->payload['data']['object'])->toHaveKeys(['id', 'object', 'not_awarded_at', 'reason', 'note']);
});

it('writes invitation.accepted to the issuer with the vendor and the coverage', function () {
    [$issuer, $competition] = integrationsIssuerCompetition(fn ($f) => $f->live());
    $supplier = Organization::factory()->create();
    $vendor = Vendor::factory()->for($issuer)->create();
    ExternalRef::factory()->create([
        'organization_id' => $issuer->id, 'refable_type' => 'vendor', 'refable_id' => $vendor->id,
        'system' => 'sap_s4', 'type' => 'supplier', 'value' => '100045',
    ]);
    $invitation = Invitation::factory()->joined()->forVendor($vendor)->create([
        'competition_id' => $competition->id, 'organization_id' => $supplier->id,
    ]);
    $participant = Participant::factory()->sponsored()->create([
        'competition_id' => $competition->id, 'organization_id' => $supplier->id, 'invitation_id' => $invitation->id,
    ]);

    app(WriteInvitationWebhooks::class)->accepted(new InvitationJoined($invitation, $participant, Actor::system()));

    $event = integrationsOutboxEvent();

    expect($event->type)->toBe('invitation.accepted')
        ->and($event->organization_id)->toBe($issuer->id)
        ->and($event->subject_type)->toBe('invitation')
        ->and($event->payload['links']['object'])->toEndWith('/api/public/v1/competitions/'.$competition->public_id.'/invitations')
        ->and($event->payload['data']['object'])->toEqual([
            'id' => $invitation->public_id,
            'object' => 'invitation',
            'competition_id' => $competition->public_id,
            'email' => $invitation->email,
            'organization' => ['id' => $supplier->public_id, 'name' => $supplier->name],
            'vendor' => ['id' => $vendor->public_id, 'external_refs' => [
                ['system' => 'sap_s4', 'type' => 'supplier', 'id' => '100045', 'number' => null, 'url' => null],
            ]],
            'sponsored' => true,
            'joined_at' => Iso::format($invitation->joined_at),
        ]);
});

it('writes invitation.declined', function () {
    [, $competition] = integrationsIssuerCompetition(fn ($f) => $f->live());
    $invitation = Invitation::factory()->declined()->create(['competition_id' => $competition->id]);

    app(WriteInvitationWebhooks::class)->declined(new InvitationDeclined($invitation));

    expect(integrationsOutboxEvent()->payload['data']['object'])->toEqual([
        'id' => $invitation->public_id,
        'object' => 'invitation',
        'competition_id' => $competition->public_id,
        'email' => $invitation->email,
        'vendor' => null,
        'declined_at' => Iso::format($invitation->declined_at),
    ]);
});

it('writes offer.submitted for the first offer and offer.updated after', function () {
    [$issuer, $competition] = integrationsIssuerCompetition(fn ($f) => $f->live());
    $participant = Participant::factory()->create(['competition_id' => $competition->id]);
    $first = Offer::factory()->create(['participant_id' => $participant->id]);
    $second = Offer::factory()->create(['participant_id' => $participant->id]);
    $event = fn (Offer $offer, ?bool $isFirst) => new class($offer, $competition, $isFirst === null ? null : new class($isFirst)
    {
        public function __construct(public bool $isFirstOfferOfParticipant) {}
    })
    {

        public function __construct(public Offer $offer, public Competition $competition, public ?object $context) {}
    };

    app(WriteOfferWebhooks::class)->accepted($event($first, true));
    $submitted = integrationsOutboxEvent();
    app(WriteOfferWebhooks::class)->accepted($event($second, null));
    $updated = integrationsOutboxEvent();

    expect($submitted->type)->toBe('offer.submitted')
        ->and($updated->type)->toBe('offer.updated')
        ->and($submitted->subject_type)->toBe('offer')
        ->and($submitted->payload['data']['object'])->toEqual([
            'id' => $first->public_id,
            'object' => 'offer',
            'competition_id' => $competition->public_id,
            'participant_id' => $participant->public_id,
            'organization' => ['id' => $participant->organization->public_id, 'name' => $participant->organization->name],
            'vendor' => null,
            'seq' => $first->seq,
            'stage' => 'live',
            'amount_minor' => $first->amount_minor,
            'currency' => 'SAR',
            'accepted_at' => Iso::format($first->accepted_at),
        ]);
});

it('hides the amount of a sealed-stage offer', function () {
    [, $competition] = integrationsIssuerCompetition(fn ($f) => $f->sealed()->live());
    $offer = Offer::factory()->stage(OfferStage::Sealed)->create([
        'participant_id' => Participant::factory()->create(['competition_id' => $competition->id])->id,
    ]);

    app(WriteOfferWebhooks::class)->accepted(new class($offer)
    {
        public function __construct(public Offer $offer) {}
    });

    expect(integrationsOutboxEvent()->payload['data']['object']['amount_minor'])->toBeNull()
        ->and(integrationsOutboxEvent()->payload['data']['object']['stage'])->toBe('sealed');
});

it('writes award.issued and award.cancelled', function () {
    [$issuer, $competition] = integrationsIssuerCompetition(fn ($f) => $f->awarded());
    $participant = Participant::factory()->create(['competition_id' => $competition->id]);
    $offer = Offer::factory()->create(['participant_id' => $participant->id]);
    $award = Award::factory()->create(['offer_id' => $offer->id]);
    $winner = $participant->organization;
    $issued = new class($award, $competition)
    {
        public function __construct(public Award $award, public Competition $competition) {}
    };

    app(WriteAwardWebhooks::class)->issued($issued);
    $event = integrationsOutboxEvent();

    expect($event->type)->toBe('award.issued')
        ->and($event->payload['links']['object'])->toBe(config('app.url').'/api/public/v1/awards/'.$award->public_id)
        ->and($event->payload['data']['object'])->toEqual([
            'id' => $award->public_id,
            'object' => 'award',
            'competition_id' => $competition->public_id,
            'status' => 'issued',
            'winner' => [
                'organization' => ['id' => $winner->public_id, 'name' => $winner->name, 'cr_number' => $winner->cr_number, 'vat_number' => $winner->vat_number],
                'vendor' => null,
            ],
            'amount_minor' => $award->amount_minor,
            'currency' => 'SAR',
            'awarded_at' => Iso::format($award->awarded_at),
        ]);

    $award->forceFill(['status' => 'revoked', 'revoked_at' => now(), 'revoke_reason' => 'Supplier withdrew'])->save();
    app(WriteAwardWebhooks::class)->revoked($issued);

    expect(integrationsOutboxEvent()->type)->toBe('award.cancelled')
        ->and(integrationsOutboxEvent()->sequence)->toBe(2)
        ->and(integrationsOutboxEvent()->payload['data']['object'])->toMatchArray(['status' => 'revoked', 'reason' => 'Supplier withdrew']);
});

it('fans an event out to the subscribed active endpoints once', function () {
    $issuer = Organization::factory()->apiEnabled()->create();
    $all = WebhookEndpoint::factory()->for($issuer)->create();
    $awards = WebhookEndpoint::factory()->for($issuer)->eventTypes(['award.issued'])->create();
    WebhookEndpoint::factory()->for($issuer)->eventTypes(['competition.closed'])->create();
    WebhookEndpoint::factory()->for($issuer)->disabled()->create();
    WebhookEndpoint::factory()->create();
    $event = WebhookEvent::factory()->create(['organization_id' => $issuer->id, 'type' => 'award.issued']);

    (new DispatchWebhookEvent($event->id))->handle();
    (new DispatchWebhookEvent($event->id))->handle();

    expect(WebhookDelivery::query()->pluck('webhook_endpoint_id')->sort()->values()->all())->toBe([$all->id, $awards->id])
        ->and($event->refresh()->dispatched_at)->not->toBeNull();

    Queue::assertPushed(DeliverWebhook::class, 2);
});
