<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\GrantSponsoredPass;
use App\Modules\Billing\Actions\IssueSponsorshipVoucher;
use App\Modules\Billing\Actions\SettleCompetitionSponsorship;
use App\Modules\Billing\Contracts\SponsorshipService;
use App\Modules\Billing\Enums\CouponKind;
use App\Modules\Billing\Enums\PassReleaseReason;
use App\Modules\Billing\Enums\PassSource;
use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Enums\SponsorshipMode;
use App\Modules\Billing\Enums\SponsorshipStatus;
use App\Modules\Billing\Events\SponsorshipSettled;
use App\Modules\Billing\Events\VoucherIssued;
use App\Modules\Billing\Listeners\ReleasePassForInvitation;
use App\Modules\Billing\Listeners\SettleSponsorship;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Enums\RevokeReason;
use App\Modules\Competitions\Events\CompetitionCancelled;
use App\Modules\Competitions\Events\CompetitionClosed;
use App\Modules\Competitions\Events\InvitationDeclined;
use App\Modules\Competitions\Events\InvitationRevoked;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Models\Organization;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\Event;
use Tests\Support\Billing\Billing;

beforeEach(fn () => Billing::plans());

function billingSponsorshipService(): SponsorshipService
{
    return app(SponsorshipService::class);
}

describe('SponsorshipService::reserveForPublish', function () {
    it('does nothing without a sponsorship', function () {
        [, $competition] = Billing::sponsoringIssuer();
        Invitation::factory()->count(2)->create(['competition_id' => $competition->id]);

        billingSponsorshipService()->reserveForPublish($competition);

        expect(SponsoredPass::query()->count())->toBe(0);
    });

    it('throws sponsorship_payment_required with the quote and writes nothing', function () {
        [, $competition] = Billing::sponsoringIssuer();
        Billing::sponsorship($competition, ['funded_passes' => 1, 'status' => 'active']);
        Invitation::factory()->count(3)->create(['competition_id' => $competition->id]);

        try {
            billingSponsorshipService()->reserveForPublish($competition);
            $this->fail('sponsorship_payment_required expected');
        } catch (ApiException $e) {
            expect($e->errorCode)->toBe('sponsorship_payment_required')
                ->and($e->status)->toBe(409)
                ->and($e->details['quote'])->toMatchArray(['passes_to_reserve' => 1, 'passes_to_buy' => 2, 'subtotal_minor' => 40_000, 'total_minor' => 46_000]);
        }

        expect(SponsoredPass::query()->count())->toBe(0);
    });

    it('reserves funded slots, then freed slots', function () {
        [, $competition] = Billing::sponsoringIssuer();
        $sponsorship = Billing::sponsorship($competition, ['funded_passes' => 3, 'status' => 'active']);
        $declined = Invitation::factory()->declined()->create(['competition_id' => $competition->id]);
        SponsoredPass::factory()->released()->create(['sponsorship_id' => $sponsorship->id, 'competition_id' => $competition->id, 'invitation_id' => $declined->id]);
        $invitations = Invitation::factory()->count(3)->create(['competition_id' => $competition->id]);

        billingSponsorshipService()->reserveForPublish($competition);

        $passes = SponsoredPass::query()->where('status', PassStatus::Reserved->value)->orderBy('id')->get();
        expect($passes)->toHaveCount(3)
            ->and($passes->pluck('invitation_id')->all())->toBe($invitations->pluck('id')->all())
            ->and($passes->pluck('source')->all())->toBe([PassSource::Purchase, PassSource::Purchase, PassSource::FreedSlot]);

        // A second call finds every invitation covered: idempotent.
        billingSponsorshipService()->reserveForPublish($competition);
        expect(SponsoredPass::query()->where('status', PassStatus::Reserved->value)->count())->toBe(3);
    });

    it('skips invitees whose own plan covers the competition', function () {
        [, $competition] = Billing::sponsoringIssuer();
        $competition->forceFill(['invitation_cutoff_at' => now()->addDays(2)])->save();
        Billing::sponsorship($competition);
        $planned = Organization::factory()->create();
        Billing::subscribe($planned, 'pro', startsAt: now()->subDay()->toImmutable(), endsAt: now()->addMonth()->toImmutable());
        Invitation::factory()->forOrganization($planned)->create(['competition_id' => $competition->id]);

        billingSponsorshipService()->reserveForPublish($competition);

        expect(SponsoredPass::query()->count())->toBe(0);
    });
});

describe('SponsorshipService::reserveForInvitations', function () {
    it('reserves free slots for new sponsored invitations of a published competition', function () {
        [, $competition] = Billing::sponsoringIssuer();
        $competition->forceFill(['status' => CompetitionStatus::Live])->save();
        Billing::sponsorship($competition, ['mode' => SponsorshipMode::Selected, 'funded_passes' => 1, 'status' => 'active']);
        $sponsored = Invitation::factory()->sent()->create(['competition_id' => $competition->id, 'sponsored_requested' => true]);
        $plain = Invitation::factory()->sent()->create(['competition_id' => $competition->id, 'sponsored_requested' => false]);

        billingSponsorshipService()->reserveForInvitations($competition, collect([$sponsored, $plain]));

        expect(SponsoredPass::query()->pluck('invitation_id')->all())->toBe([$sponsored->id]);
    });

    it('refuses when the free slots are not enough', function () {
        [, $competition] = Billing::sponsoringIssuer();
        $competition->forceFill(['status' => CompetitionStatus::Live])->save();
        Billing::sponsorship($competition, ['funded_passes' => 1, 'status' => 'active']);
        $invitations = Invitation::factory()->count(2)->sent()->create(['competition_id' => $competition->id]);

        expect(fn () => billingSponsorshipService()->reserveForInvitations($competition, $invitations))
            ->toThrow(fn (ApiException $e) => expect($e->errorCode)->toBe('sponsorship_payment_required'));
        expect(SponsoredPass::query()->count())->toBe(0);
    });

    it('leaves draft competitions to the publish', function () {
        [, $competition] = Billing::sponsoringIssuer();
        Billing::sponsorship($competition);
        $invitations = Invitation::factory()->count(2)->create(['competition_id' => $competition->id]);

        billingSponsorshipService()->reserveForInvitations($competition, $invitations);

        expect(SponsoredPass::query()->count())->toBe(0);
    });
});

describe('release on decline and revoke', function () {
    it('binds the synchronous listeners to the Competitions events', function () {
        expect(Event::hasListeners(InvitationDeclined::class))->toBeTrue()
            ->and(Event::hasListeners(InvitationRevoked::class))->toBeTrue()
            ->and(Event::hasListeners(CompetitionClosed::class))->toBeTrue()
            ->and(Event::hasListeners(CompetitionCancelled::class))->toBeTrue();
    });

    it('releases a reserved pass (its slot is free again) and voids a pending one', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        $sponsorship = Billing::sponsorship($competition, ['funded_passes' => 1, 'status' => 'active']);
        $reserved = SponsoredPass::factory()->create(['sponsorship_id' => $sponsorship->id, 'competition_id' => $competition->id]);
        $pending = SponsoredPass::factory()->pending()->create(['sponsorship_id' => $sponsorship->id, 'competition_id' => $competition->id]);
        $listener = app(ReleasePassForInvitation::class);

        $listener->onDeclined(new InvitationDeclined($reserved->invitation));
        $listener->onRevoked(new InvitationRevoked($pending->invitation, Actor::forUser($owner)));

        expect($reserved->refresh()->status)->toBe(PassStatus::Released)
            ->and($reserved->release_reason)->toBe(PassReleaseReason::Declined)
            ->and($pending->refresh()->status)->toBe(PassStatus::Void);

        // The freed slot funds the next invitation without a new payment.
        $competition->forceFill(['status' => CompetitionStatus::Live])->save();
        $next = Invitation::factory()->sent()->create(['competition_id' => $competition->id]);
        billingSponsorshipService()->reserveForInvitations($competition, collect([$next]));
        expect(SponsoredPass::query()->where('invitation_id', $next->id)->value('source'))->toBe(PassSource::FreedSlot);
    });

    it('records a duplicate organization revoke', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        $pass = SponsoredPass::factory()->create(['sponsorship_id' => Billing::sponsorship($competition, ['funded_passes' => 1])->id, 'competition_id' => $competition->id]);
        $pass->invitation->forceFill(['status' => InvitationStatus::Revoked, 'revoke_reason' => RevokeReason::DuplicateOrganization])->save();

        app(ReleasePassForInvitation::class)->onRevoked(new InvitationRevoked($pass->invitation, Actor::forUser($owner)));

        expect($pass->refresh()->release_reason)->toBe(PassReleaseReason::DuplicateOrganization);
    });
});

describe('settlement and vouchers', function () {
    it('settles at close: reserved → unused, pending → void, unused_count', function () {
        Event::fake([SponsorshipSettled::class]);
        [, $competition] = Billing::sponsoringIssuer(['reference_no' => 'BAFO-T-2026-000123']);
        $sponsorship = Billing::sponsorship($competition, ['funded_passes' => 4, 'status' => 'active']);
        $unused = SponsoredPass::factory()->create(['sponsorship_id' => $sponsorship->id, 'competition_id' => $competition->id]);
        SponsoredPass::factory()->create(['sponsorship_id' => $sponsorship->id, 'competition_id' => $competition->id, 'status' => PassStatus::Joined]);
        $pending = SponsoredPass::factory()->pending()->create(['sponsorship_id' => $sponsorship->id, 'competition_id' => $competition->id]);

        app(SettleSponsorship::class)->handle(new CompetitionClosed($competition));

        expect($sponsorship->refresh()->status)->toBe(SponsorshipStatus::Settled)
            ->and($sponsorship->unused_count)->toBe(3)
            ->and($unused->refresh()->status)->toBe(PassStatus::Unused)
            ->and($pending->refresh()->status)->toBe(PassStatus::Void);
        Event::assertDispatched(SponsorshipSettled::class);

        // Settlement is final and happens once (cancel after close changes nothing).
        app(SettleSponsorship::class)->handle(new CompetitionCancelled($competition, Actor::system()));
        Event::assertDispatchedTimes(SponsorshipSettled::class, 1);
    });

    it('issues a voucher worth the unused passes once', function () {
        Event::fake([VoucherIssued::class]);
        [, $competition] = Billing::sponsoringIssuer(['reference_no' => 'BAFO-T-2026-000123']);
        $sponsorship = Billing::sponsorship($competition, ['funded_passes' => 3, 'status' => 'active']);
        SponsoredPass::factory()->create(['sponsorship_id' => $sponsorship->id, 'competition_id' => $competition->id, 'status' => PassStatus::Joined]);

        app(SettleCompetitionSponsorship::class)->handle($competition, Actor::system());
        $voucher = app(IssueSponsorshipVoucher::class)->handle($sponsorship->refresh(), Actor::system());

        expect($voucher->kind)->toBe(CouponKind::Voucher)
            ->and($voucher->code)->toMatch('/^V-[A-Z0-9]{10}$/')
            ->and($voucher->amount_minor)->toBe(40_000)
            ->and($voucher->balance_minor)->toBe(40_000)
            ->and($voucher->organization_id)->toBe($competition->organization_id)
            ->and($voucher->source_competition_id)->toBe($competition->id)
            ->and($voucher->reason)->toBe('Unused passes BAFO-T-2026-000123')
            ->and($voucher->valid_until?->isAfter(now()->addMonths(11)))->toBeTrue()
            ->and($sponsorship->refresh()->voucher_coupon_id)->toBe($voucher->id);
        Event::assertDispatched(VoucherIssued::class);

        expect(fn () => app(IssueSponsorshipVoucher::class)->handle($sponsorship, Actor::system()))
            ->toThrow(fn (ApiException $e) => expect($e->errorCode)->toBe('invalid_state_transition'));
    });

    it('shows the voucher to the issuer billing users', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        $sponsorship = Billing::sponsorship($competition, ['funded_passes' => 1, 'status' => 'active']);
        app(SettleCompetitionSponsorship::class)->handle($competition, Actor::system());
        $voucher = app(IssueSponsorshipVoucher::class)->handle($sponsorship->refresh(), Actor::system());
        Billing::actingAs($owner);

        $this->getJson('/api/app/v1/billing/vouchers')->assertOk()->assertJsonPath('data.0.code', $voucher->code)
            ->assertJsonPath('data.0.source_competition_id', $competition->public_id);
        $this->getJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship")->assertJsonPath('data.voucher.code', $voucher->code)
            ->assertJsonPath('data.status', 'settled')
            ->assertJsonPath('data.unused_count', 1);
    });

    it('refuses a voucher before settlement', function () {
        [, $competition] = Billing::sponsoringIssuer();
        $sponsorship = Billing::sponsorship($competition, ['funded_passes' => 1, 'status' => 'active']);

        expect(fn () => app(IssueSponsorshipVoucher::class)->handle($sponsorship, Actor::system()))->toThrow(ApiException::class);
    });
});

describe('admin pass grant', function () {
    it('funds one slot and reserves it for the invitation', function () {
        [, $competition] = Billing::sponsoringIssuer();
        $sponsorship = Billing::sponsorship($competition);
        $invitation = Invitation::factory()->create(['competition_id' => $competition->id]);

        $pass = app(GrantSponsoredPass::class)->handle($invitation, Actor::system());

        expect($pass->source)->toBe(PassSource::AdminGrant)
            ->and($pass->status)->toBe(PassStatus::Reserved)
            ->and($sponsorship->refresh()->funded_passes)->toBe(1)
            ->and($sponsorship->status)->toBe(SponsorshipStatus::Active);

        // The publish then finds the invitation covered.
        billingSponsorshipService()->reserveForPublish($competition);
        expect(SponsoredPass::query()->count())->toBe(1);

        expect(fn () => app(GrantSponsoredPass::class)->handle($invitation, Actor::system()))->toThrow(ApiException::class);
    });
});
