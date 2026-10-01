<?php

declare(strict_types=1);

use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Billing\Enums\AccessState;
use App\Modules\Billing\Enums\Coverage;
use App\Modules\Billing\Enums\EntitlementSource;
use App\Modules\Billing\Enums\PassReleaseReason;
use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Models\Organization;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Tests\Support\Billing\Billing;

beforeEach(fn () => Billing::plans());

function billingAccessPolicy(): AccessPolicy
{
    return app(AccessPolicy::class);
}

/**
 * A sent invitation of a live competition (issuer "شركة المصدر") to a new organization.
 *
 * @return array{0: Organization, 1: Invitation}
 */
function billingLiveInvitation(array $competition = [], array $invitation = []): array
{
    $issuer = Organization::factory()->sponsorshipEnabled()->create(['name' => 'شركة المصدر']);
    $model = Competition::factory()->live()->create(['organization_id' => $issuer->id, ...$competition]);
    $invitee = Organization::factory()->create();

    return [$invitee, Invitation::factory()->sent()->create([
        'competition_id' => $model->id,
        'organization_id' => $invitee->id,
        'email' => $invitee->email,
        ...$invitation,
    ])];
}

function billingReservePass(Invitation $invitation, PassStatus $status = PassStatus::Reserved): SponsoredPass
{
    $sponsorship = Billing::sponsorship($invitation->competition, ['funded_passes' => 1, 'status' => 'active']);

    return SponsoredPass::factory()->create([
        'sponsorship_id' => $sponsorship->id,
        'competition_id' => $invitation->competition_id,
        'invitation_id' => $invitation->id,
        'status' => $status,
        'hold_expires_at' => $status === PassStatus::Pending ? now()->addMinutes(30) : null,
    ]);
}

describe('canIssue, activeSubscription and seatLimit', function () {
    it('needs an active organization with a current subscription', function (Closure $setup, bool $canIssue, int $seats) {
        $organization = Organization::factory()->create();
        $setup($organization);

        expect(billingAccessPolicy()->canIssue($organization->refresh()))->toBe($canIssue)
            ->and(billingAccessPolicy()->seatLimit($organization))->toBe($seats);
    })->with([
        'no subscription' => [fn () => null, false, 1],
        'paid pro' => [fn (Organization $o) => Billing::subscribe($o, 'pro'), true, 3],
        'trial plus' => [fn (Organization $o) => Billing::subscribe($o, 'plus', SubscriptionSource::Trial), true, 2],
        'grant' => [fn (Organization $o) => Billing::subscribe($o, 'single', SubscriptionSource::Grant), true, 1],
        'ended' => [fn (Organization $o) => Billing::subscribe($o, 'pro', startsAt: CarbonImmutable::now()->subMonths(2), endsAt: CarbonImmutable::now()->subSecond()), false, 1],
        'not started yet' => [fn (Organization $o) => Billing::subscribe($o, 'pro', startsAt: CarbonImmutable::now()->addDay()), false, 1],
        'pending payment' => [fn (Organization $o) => Billing::subscribe($o, 'pro')->forceFill(['status' => SubscriptionStatus::PendingPayment])->save(), false, 1],
        'suspended organization' => [function (Organization $o): void {
            Billing::subscribe($o, 'pro');
            $o->forceFill(['status' => OrganizationStatus::Suspended])->save();
        }, false, 3],
    ]);
});

describe('participationAccess (§8.4 matrix)', function () {
    it('gives full access to a participant while the competition runs, read-only afterwards', function (string $status, AccessState $state) {
        [$invitee, $invitation] = billingLiveInvitation();
        Participant::factory()->create([
            'competition_id' => $invitation->competition_id, 'organization_id' => $invitee->id, 'invitation_id' => $invitation->id,
            'entitlement_source' => EntitlementSource::SponsoredPass,
        ]);
        $invitation->competition->forceFill(['status' => $status])->save();

        $access = billingAccessPolicy()->participationAccess($invitee, $invitation->refresh());

        expect($access->state)->toBe($state)
            ->and($access->coverage)->toBe(Coverage::Sponsored)
            ->and($access->sponsorName)->toBe('شركة المصدر');
    })->with([
        ['live', AccessState::Full],
        ['bafo_round', AccessState::Full],
        ['closed', AccessState::ReadOnly],
        ['awarded', AccessState::ReadOnly],
    ]);

    it('keeps the participation lock-in: a lapsed plan does not change a joined participant', function () {
        [$invitee, $invitation] = billingLiveInvitation();
        Participant::factory()->create([
            'competition_id' => $invitation->competition_id, 'organization_id' => $invitee->id, 'invitation_id' => $invitation->id,
            'entitlement_source' => EntitlementSource::Plan,
        ]);

        $access = billingAccessPolicy()->participationAccess($invitee, $invitation);

        expect($access->state)->toBe(AccessState::Full)->and($access->coverage)->toBe(Coverage::OwnPlan)->and($access->sponsorName)->toBeNull();
    });

    it('covers an invitee with a reserved pass', function () {
        [$invitee, $invitation] = billingLiveInvitation();
        billingReservePass($invitation);

        expect(billingAccessPolicy()->participationAccess($invitee, $invitation)->toArray())->toMatchArray([
            'state' => 'join_required', 'coverage' => 'sponsored', 'sponsor_name' => 'شركة المصدر',
        ]);
    });

    it('asks for the own plan or a plan', function () {
        [$withPlan, $invitation] = billingLiveInvitation();
        Billing::subscribe($withPlan, 'single');
        [$withoutPlan, $other] = billingLiveInvitation();

        expect(billingAccessPolicy()->participationAccess($withPlan, $invitation)->toArray())->toMatchArray(['state' => 'join_required', 'coverage' => 'own_plan', 'sponsor_name' => null])
            ->and(billingAccessPolicy()->participationAccess($withoutPlan, $other)->toArray())->toMatchArray(['state' => 'plan_required', 'coverage' => 'none']);
    });

    it('is unavailable after decline, after the cutoff or outside scheduled and live', function (Closure $mutate) {
        [$invitee, $invitation] = billingLiveInvitation();
        billingReservePass($invitation);
        $mutate($invitation);

        $access = billingAccessPolicy()->participationAccess($invitee, $invitation->refresh());

        expect($access->state)->toBe(AccessState::Unavailable)->and($access->coverage)->toBe(Coverage::None);
    })->with([
        'declined' => [fn (Invitation $i) => $i->forceFill(['status' => InvitationStatus::Declined])->save()],
        'expired' => [fn (Invitation $i) => $i->forceFill(['status' => InvitationStatus::Expired])->save()],
        'cutoff passed' => [fn (Invitation $i) => $i->competition->forceFill(['invitation_cutoff_at' => now()->subSecond()])->save()],
        'closed' => [fn (Invitation $i) => $i->competition->forceFill(['status' => 'closed'])->save()],
    ]);

    it('returns the join deadline', function () {
        [$invitee, $invitation] = billingLiveInvitation();

        expect(billingAccessPolicy()->participationAccess($invitee, $invitation)->toArray()['join_deadline'])
            ->toBe($invitation->competition->invitation_cutoff_at?->utc()->format('Y-m-d\TH:i:s.v\Z'));
    });
});

describe('resolveJoin (pass consumption)', function () {
    it('consumes the reserved pass of an invitee without a plan', function () {
        [$invitee, $invitation] = billingLiveInvitation();
        $pass = billingReservePass($invitation);

        expect(billingAccessPolicy()->resolveJoin($invitee, $invitation))->toBe(EntitlementSource::SponsoredPass)
            ->and($pass->refresh()->status)->toBe(PassStatus::Joined)
            ->and($pass->organization_id)->toBe($invitee->id)
            ->and($pass->joined_at)->not->toBeNull();
    });

    it('releases the pass when the invitee has its own plan (covered_by_own_plan)', function () {
        [$invitee, $invitation] = billingLiveInvitation();
        Billing::subscribe($invitee, 'pro');
        $pass = billingReservePass($invitation);

        expect(billingAccessPolicy()->resolveJoin($invitee, $invitation))->toBe(EntitlementSource::Plan)
            ->and($pass->refresh()->status)->toBe(PassStatus::Released)
            ->and($pass->release_reason)->toBe(PassReleaseReason::CoveredByOwnPlan);
    });

    it('returns grant for an admin-granted subscription', function () {
        [$invitee, $invitation] = billingLiveInvitation();
        Billing::subscribe($invitee, 'pro', SubscriptionSource::Grant);

        expect(billingAccessPolicy()->resolveJoin($invitee, $invitation))->toBe(EntitlementSource::Grant);
    });

    it('ignores a pending (unpaid) pass and refuses with plan_required', function () {
        [$invitee, $invitation] = billingLiveInvitation();
        billingReservePass($invitation, PassStatus::Pending);

        try {
            billingAccessPolicy()->resolveJoin($invitee, $invitation);
            $this->fail('plan_required expected');
        } catch (ApiException $e) {
            expect($e->errorCode)->toBe('plan_required')
                ->and($e->status)->toBe(403)
                ->and($e->details['access'])->toMatchArray(['state' => 'plan_required', 'coverage' => 'none']);
        }
    });

    it('lets a pass be consumed only once', function () {
        [$invitee, $invitation] = billingLiveInvitation();
        billingReservePass($invitation);

        billingAccessPolicy()->resolveJoin($invitee, $invitation);

        expect(fn () => billingAccessPolicy()->resolveJoin($invitee, $invitation))->toThrow(ApiException::class);
    });
});

describe('coverageFor', function () {
    it('reports sponsored, own plan or none per invitation', function () {
        [$sponsoredOrg, $sponsored] = billingLiveInvitation();
        $competition = $sponsored->competition;
        billingReservePass($sponsored, PassStatus::Pending);

        $longPlan = Organization::factory()->create();
        Billing::subscribe($longPlan, 'pro', startsAt: CarbonImmutable::now()->subDay(), endsAt: CarbonImmutable::now()->addMonth());
        $ownPlan = Invitation::factory()->sent()->forOrganization($longPlan)->create(['competition_id' => $competition->id]);

        $shortPlan = Organization::factory()->create();
        Billing::subscribe($shortPlan, 'pro', startsAt: CarbonImmutable::now()->subDays(29), endsAt: CarbonImmutable::now()->addMinutes(10));
        $expiring = Invitation::factory()->sent()->forOrganization($shortPlan)->create(['competition_id' => $competition->id]);

        $unknown = Invitation::factory()->sent()->create(['competition_id' => $competition->id]);

        $coverage = billingAccessPolicy()->coverageFor([$sponsored, $ownPlan, $expiring, $unknown]);

        expect($coverage)->toBe([
            $sponsored->id => Coverage::Sponsored,
            $ownPlan->id => Coverage::OwnPlan,
            $expiring->id => Coverage::None,
            $unknown->id => Coverage::None,
        ]);
    });

    it('counts a queued renewal in the own plan coverage', function () {
        [$invitee, $invitation] = billingLiveInvitation();
        Billing::subscribe($invitee, 'pro', startsAt: CarbonImmutable::now()->subDays(29), endsAt: CarbonImmutable::now()->addMinutes(10));
        Billing::subscribe($invitee, 'pro', startsAt: CarbonImmutable::now()->addMinutes(10), endsAt: CarbonImmutable::now()->addMonth());

        expect(billingAccessPolicy()->coverageFor([$invitation]))->toBe([$invitation->id => Coverage::OwnPlan]);
    });
});

it('joins on a sponsored pass through the invitation join endpoint', function () {
    [$invitee, $invitation] = billingLiveInvitation();
    $pass = billingReservePass($invitation);
    Billing::actingAs(Billing::member($invitee));

    $this->postJson('/api/app/v1/invitations/'.$invitation->public_id.'/join', ['accept_terms' => true])->assertOk();

    expect($pass->refresh()->status)->toBe(PassStatus::Joined)
        ->and(Participant::query()->where('invitation_id', $invitation->id)->value('entitlement_source'))->toBe(EntitlementSource::SponsoredPass);
})->group('cross-module');
