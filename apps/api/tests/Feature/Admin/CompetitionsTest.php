<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Resources\Competitions\Pages\ListCompetitions;
use App\Modules\Admin\Filament\Resources\Competitions\Pages\ViewCompetition;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\AwardsRelationManager;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\ExtensionsRelationManager;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\InvitationsRelationManager;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\OffersRelationManager;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\ParticipantsRelationManager;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\RejectionsRelationManager;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\OfferRejection;
use App\Modules\Bidding\Models\OfferVoid;
use App\Modules\Billing\Enums\PassSource;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\ExtensionKind;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Enums\MustBeat;
use App\Modules\Competitions\Enums\RevokeReason;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionExtension;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\Admin\AdminPanel;
use Tests\Support\Bidding\Scenario;

/*
 * §16 Competitions: list with a status filter; view with the timeline, invitations, participants,
 * the offer ledger (issuer projection plus voids), extensions, rejections and the award. Extend,
 * Cancel, Force close (Competitions) and Void offer (Bidding) run the module Actions as the admin.
 */

beforeEach(function () {
    Mail::fake();
    $this->admin = AdminPanel::signIn(AdminPanel::operator());
});

function adminRelationContext(Competition $competition): array
{
    return ['ownerRecord' => $competition, 'pageClass' => ViewCompetition::class];
}

it('lists competitions with a status filter', function () {
    $live = Competition::factory()->withoutFinalWindow()->live()->create();
    $draft = Competition::factory()->create();

    Livewire::test(ListCompetitions::class)
        ->assertCanSeeTableRecords([$live, $draft])
        ->filterTable('status', [CompetitionStatus::Live->value])
        ->assertCanSeeTableRecords([$live])
        ->assertCanNotSeeTableRecords([$draft]);
});

it('shows a competition with every relation of §16', function () {
    $competition = Competition::factory()->closed()->create(['title' => 'توريد أجهزة']);
    $invitation = Invitation::factory()->joined()->create(['competition_id' => $competition->id]);
    $participant = Participant::factory()->create(['competition_id' => $competition->id, 'invitation_id' => $invitation->id]);
    $rejection = OfferRejection::factory()->create(['competition_id' => $competition->id, 'participant_id' => $participant->id]);
    $extension = CompetitionExtension::factory()->create(['competition_id' => $competition->id]);

    $this->get('/admin/competitions/'.$competition->public_id)->assertOk()->assertSee('توريد أجهزة');

    Livewire::test(InvitationsRelationManager::class, adminRelationContext($competition))->assertCanSeeTableRecords([$invitation]);
    Livewire::test(ParticipantsRelationManager::class, adminRelationContext($competition))->assertCanSeeTableRecords([$participant]);
    Livewire::test(RejectionsRelationManager::class, adminRelationContext($competition))->assertCanSeeTableRecords([$rejection]);
    Livewire::test(ExtensionsRelationManager::class, adminRelationContext($competition))->assertCanSeeTableRecords([$extension]);
    Livewire::test(OffersRelationManager::class, adminRelationContext($competition))->assertOk();
    Livewire::test(AwardsRelationManager::class, adminRelationContext($competition))->assertOk();
});

it('shows the award and any revoked awards', function () {
    $competition = Competition::factory()->awarded()->create();
    $award = Award::factory()->create(['competition_id' => $competition->id]);

    Livewire::test(AwardsRelationManager::class, adminRelationContext($competition))->assertCanSeeTableRecords([$award]);
});

it('extends a live competition as an admin extension', function () {
    $competition = Competition::factory()->withoutFinalWindow()->live()->create();
    $newClose = $competition->effective_close_at->addHour()->startOfMinute();

    Livewire::test(ViewCompetition::class, ['record' => $competition->public_id])
        // The picker shows Asia/Riyadh time; the Action receives UTC.
        ->callAction('extend', data: ['new_close_at' => $newClose->setTimezone('Asia/Riyadh')->toDateTimeString(), 'reason' => 'Supplier portal outage'])
        ->assertHasNoActionErrors();

    $extension = CompetitionExtension::query()->where('competition_id', $competition->id)->sole();
    expect($extension->kind)->toBe(ExtensionKind::Admin)
        ->and($extension->actor_admin_id)->toBe($this->admin->id)
        ->and($competition->refresh()->effective_close_at->equalTo($newClose))->toBeTrue();
});

it('reports extend_invalid and keeps the close time', function () {
    $competition = Competition::factory()->withoutFinalWindow()->live()->create();
    $close = $competition->effective_close_at;

    Livewire::test(ViewCompetition::class, ['record' => $competition->public_id])
        ->callAction('extend', data: ['new_close_at' => $close->addMinute()->setTimezone('Asia/Riyadh')->toDateTimeString(), 'reason' => 'Too short an extension'])
        ->assertNotified();

    expect($competition->refresh()->effective_close_at->equalTo($close))->toBeTrue()
        ->and(CompetitionExtension::query()->where('competition_id', $competition->id)->exists())->toBeFalse();
});

it('cancels with a cancel reason and requires the note of an "other" reason', function () {
    $competition = Competition::factory()->scheduled()->create();
    $other = CloseReason::factory()->kind(CloseReasonKind::Cancel)->requiresNote()->create();
    $reason = CloseReason::factory()->kind(CloseReasonKind::Cancel)->create();

    Livewire::test(ViewCompetition::class, ['record' => $competition->public_id])
        ->callAction('cancel', data: ['reason_id' => $other->id, 'note' => ''])
        ->assertHasActionErrors(['note' => 'required']);

    Livewire::test(ViewCompetition::class, ['record' => $competition->public_id])
        ->callAction('cancel', data: ['reason_id' => $reason->id])
        ->assertHasNoActionErrors();

    $competition->refresh();
    expect($competition->status)->toBe(CompetitionStatus::Cancelled)
        ->and($competition->cancel_reason_id)->toBe($reason->id)
        ->and($competition->cancelled_by_admin_id)->toBe($this->admin->id);
});

it('force-closes a live competition', function () {
    $competition = Competition::factory()->withoutFinalWindow()->live()->create();

    Livewire::test(ViewCompetition::class, ['record' => $competition->public_id])
        ->callAction('forceClose', data: ['reason' => 'Court order received'])
        ->assertHasNoActionErrors();

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Closed)
        ->and(CompetitionExtension::query()->where('competition_id', $competition->id)->value('reason'))->toBe('Court order received');
});

it('offers extend and force close only while live, and cancel only before an outcome', function () {
    $closed = Competition::factory()->closed()->create();

    Livewire::test(ViewCompetition::class, ['record' => $closed->public_id])
        ->assertActionHidden('extend')
        ->assertActionHidden('forceClose')
        ->assertActionHidden('cancel');
});

it('voids an offer through VoidOffer and keeps the ledger row', function () {
    $s = Scenario::make($this, fn (CompetitionFactory $f) => $f->withoutFinalWindow()->live(), 2, [
        'start_price_minor' => 10_000_000,
        'min_step_bps' => 50,
        'min_step_minor' => null,
        'must_beat' => MustBeat::Own,
    ]);
    $s->offer(0, 9_600_000)->assertCreated();
    $s->offer(1, 9_500_000)->assertCreated();
    $offer = Offer::query()->where('competition_id', $s->competition->id)->where('seq', 2)->sole();
    $reason = CloseReason::factory()->kind(CloseReasonKind::VoidOffer)->create();
    AdminPanel::signIn($this->admin);

    Livewire::test(OffersRelationManager::class, adminRelationContext($s->competition))
        ->assertCanSeeTableRecords([$offer])
        ->callAction(TestAction::make('void')->table($offer), data: ['reason_id' => $reason->id, 'note' => 'Typo confirmed'])
        ->assertHasNoActionErrors();

    $void = OfferVoid::query()->where('offer_id', $offer->id)->sole();
    expect($void->voided_by_admin_id)->toBe($this->admin->id)
        ->and($void->note)->toBe('Typo confirmed')
        ->and(Offer::query()->whereKey($offer->id)->exists())->toBeTrue();

    Livewire::test(OffersRelationManager::class, adminRelationContext($s->competition))
        ->assertActionHidden(TestAction::make('void')->table($offer));
});

it('hides sealed amounts in the ledger until the offers are opened (issuer projection)', function () {
    $competition = Competition::factory()->sealed()->live()->create();
    $offer = Offer::factory()->create(['competition_id' => $competition->id, 'amount_minor' => 7_654_300]);

    Livewire::test(OffersRelationManager::class, adminRelationContext($competition))
        ->assertCanSeeTableRecords([$offer])
        ->assertSee(__('admin.common.sealed'))
        ->assertDontSee('76,543.00');
});

it('revokes a sent invitation with the admin reason', function () {
    $competition = Competition::factory()->withoutFinalWindow()->live()->create();
    $invitation = Invitation::factory()->sent()->create(['competition_id' => $competition->id]);

    Livewire::test(InvitationsRelationManager::class, adminRelationContext($competition))
        ->callAction(TestAction::make('revoke')->table($invitation));

    $invitation->refresh();
    expect($invitation->status)->toBe(InvitationStatus::Revoked)
        ->and($invitation->revoke_reason)->toBe(RevokeReason::Admin);
});

it('grants a sponsored pass for an invitation', function () {
    $competition = Competition::factory()->scheduled()->create();
    CompetitionSponsorship::factory()->selected()->create(['competition_id' => $competition->id, 'organization_id' => $competition->organization_id]);
    $invitation = Invitation::factory()->sent()->create(['competition_id' => $competition->id]);

    Livewire::test(InvitationsRelationManager::class, adminRelationContext($competition))
        ->callAction(TestAction::make('grantPass')->table($invitation));

    expect(SponsoredPass::query()->where('invitation_id', $invitation->id)->value('source'))->toBe(PassSource::AdminGrant);
});
