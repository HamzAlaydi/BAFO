<?php

declare(strict_types=1);

use App\Modules\Admin\Models\Admin;
use App\Modules\Bidding\Actions\VoidOffer;
use App\Modules\Bidding\Events\OfferVoided;
use App\Modules\Bidding\Models\CompetitionLiveState;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\OfferVoid;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\MustBeat;
use App\Support\Audit\AuditLog;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\Event;
use Tests\Support\Bidding\Scenario;

/*
 * Voiding an offer (ARCHITECTURE §7.13): platform admin only; the ledger stays unchanged, the
 * standing is rebuilt from the ledger minus voids, the competition is re-ranked.
 */

function voidScenario(object $test): Scenario
{
    $s = Scenario::make($test, fn (CompetitionFactory $f) => $f->withoutFinalWindow()->live(), 2, [
        'start_price_minor' => 10_000_000,
        'min_step_bps' => 50,
        'min_step_minor' => null,
        'must_beat' => MustBeat::Own,
    ]);

    $s->offer(0, 9_600_000)->assertCreated(); // seq 1
    $s->offer(1, 9_500_000)->assertCreated(); // seq 2
    $s->offer(0, 9_400_000)->assertCreated(); // seq 3: participant 0 leads

    return $s;
}

function adminActor(): Actor
{
    return Actor::forAdmin(Admin::factory()->create());
}

function voidReason(bool $requiresNote = false): CloseReason
{
    $factory = CloseReason::factory()->kind(CloseReasonKind::VoidOffer);

    return ($requiresNote ? $factory->requiresNote() : $factory)->create();
}

it('voids an offer, rebuilds the standing and re-ranks without touching the ledger', function () {
    Event::fake([OfferVoided::class]);
    $s = voidScenario($this);
    $offer = Offer::query()->where('seq', 3)->where('competition_id', $s->competition->id)->sole();
    $hashes = Offer::query()->orderBy('seq')->pluck('hash')->all();
    $version = CompetitionLiveState::query()->findOrFail($s->competition->id)->version;

    $void = app(VoidOffer::class)->handle($offer, voidReason(), 'Entered in error.', adminActor());

    $standing = ParticipantStanding::query()->findOrFail($s->participant(0)->id);
    $state = CompetitionLiveState::query()->findOrFail($s->competition->id);

    expect($void->offer_id)->toBe($offer->id)
        ->and($standing->current_amount_minor)->toBe(9_600_000)
        ->and($standing->current_seq)->toBe(1)
        ->and($standing->offers_count)->toBe(1)
        ->and($standing->first_amount_minor)->toBe(9_600_000)
        ->and($standing->rank)->toBe(2)
        ->and(ParticipantStanding::query()->findOrFail($s->participant(1)->id)->is_leader)->toBeTrue()
        ->and($state->leader_participant_id)->toBe($s->participant(1)->id)
        ->and($state->accepted_offer_count)->toBe(2)
        ->and($state->version)->toBe($version + 1)
        ->and($state->last_seq)->toBe(3)
        ->and(Offer::query()->orderBy('seq')->pluck('hash')->all())->toBe($hashes)
        ->and(Offer::query()->count())->toBe(3)
        ->and(AuditLog::query()->where('action', 'offer.voided')->where('organization_id', $s->issuerOrganization->id)->exists())->toBeTrue();

    Event::assertDispatched(OfferVoided::class);

    // The issuer log and the participant's own list flag the void.
    $s->actingAs($s->issuer);
    $this->getJson($s->url('offers/log'))->assertJsonPath('data.2.voided', true)->assertJsonPath('data.0.voided', false);

    $s->actingAs($s->bidder(0));
    $this->getJson($s->url('my-offers'))->assertJsonPath('data.0.voided', true);
    $this->getJson($s->url('live'))->assertJsonPath('data.my_offer.amount_minor', 9_600_000)->assertJsonPath('data.my_offers_count', 1);

    // The next offer is bounded by the rebuilt current offer.
    $s->offer(0, 9_600_000)->assertUnprocessable()->assertJsonPath('details.required_amount_minor', 9_552_000);
});

it('clears a standing whose only offer is voided', function () {
    $s = Scenario::make($this, attributes: ['start_price_minor' => 10_000_000]);
    $s->offer(0, 9_600_000)->assertCreated();

    app(VoidOffer::class)->handle(Offer::query()->sole(), voidReason(), null, adminActor());

    $standing = ParticipantStanding::query()->findOrFail($s->participant(0)->id);
    $state = CompetitionLiveState::query()->findOrFail($s->competition->id);

    expect($standing->current_offer_id)->toBeNull()
        ->and($standing->rank)->toBeNull()
        ->and($standing->offers_count)->toBe(0)
        ->and($state->leader_participant_id)->toBeNull()
        ->and($state->participants_with_offers)->toBe(0)
        ->and($state->accepted_offer_count)->toBe(0);
});

it('is reserved to platform admins', function () {
    $s = voidScenario($this);

    expect(fn () => app(VoidOffer::class)->handle(Offer::query()->firstOrFail(), voidReason(), null, Actor::forUser($s->issuer)))
        ->toThrow(fn (ApiException $e) => expect($e->errorCode)->toBe('forbidden'));
});

it('checks the reason kind and the note', function () {
    $s = voidScenario($this);
    $offer = Offer::query()->firstOrFail();

    expect(fn () => app(VoidOffer::class)->handle($offer, CloseReason::factory()->kind(CloseReasonKind::Cancel)->create(), null, adminActor()))
        ->toThrow(fn (ApiException $e) => expect($e->errorCode)->toBe('validation_failed')->and($e->errors)->toHaveKey('reason_id'));

    expect(fn () => app(VoidOffer::class)->handle($offer, voidReason(requiresNote: true), '  ', adminActor()))
        ->toThrow(fn (ApiException $e) => expect($e->errors)->toHaveKey('note'));

    expect(OfferVoid::query()->count())->toBe(0);
});

it('refuses a second void and a void in a final status', function () {
    $s = voidScenario($this);
    $offer = Offer::query()->firstOrFail();
    app(VoidOffer::class)->handle($offer, voidReason(), null, adminActor());

    expect(fn () => app(VoidOffer::class)->handle($offer, voidReason(), null, adminActor()))
        ->toThrow(fn (ApiException $e) => expect($e->errorCode)->toBe('invalid_state_transition'));

    $s->refresh()->forceFill(['status' => CompetitionStatus::Awarded])->save();

    expect(fn () => app(VoidOffer::class)->handle(Offer::query()->where('seq', 2)->firstOrFail(), voidReason(), null, adminActor()))
        ->toThrow(fn (ApiException $e) => expect($e->errorCode)->toBe('invalid_state_transition')->and($e->details)->toBe(['status' => 'awarded']));
});
