<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Billing\Data\ParticipationAccess;
use App\Modules\Billing\Enums\AccessState;
use App\Modules\Billing\Enums\Coverage;
use App\Modules\Billing\Enums\EntitlementSource;
use App\Modules\Billing\Enums\PassReleaseReason;
use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLogger;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;

/**
 * The `AccessPolicy` of ARCHITECTURE §8.4. Participation lock-in: once joined, the
 * `participants` row is the entitlement and access never depends on the plan again.
 */
final readonly class DbAccessPolicy implements AccessPolicy
{
    public function __construct(private SubscriptionLookup $subscriptions) {}

    public function canIssue(Organization $org): bool
    {
        return $org->isActive() && $this->activeSubscription($org) !== null;
    }

    public function activeSubscription(Organization $org): ?Subscription
    {
        return $this->subscriptions->current($org->id);
    }

    public function seatLimit(Organization $org): int
    {
        return $this->activeSubscription($org)->seats ?? 1;
    }

    public function participationAccess(Organization $org, Invitation $invitation): ParticipationAccess
    {
        $competition = $invitation->competition;
        $deadline = $competition->invitation_cutoff_at;
        $participant = Participant::query()
            ->where('competition_id', $competition->id)
            ->where('organization_id', $org->id)
            ->first();

        if ($participant !== null) {
            $coverage = $participant->entitlement_source === EntitlementSource::SponsoredPass ? Coverage::Sponsored : Coverage::OwnPlan;
            $state = in_array($competition->status, [CompetitionStatus::Scheduled, CompetitionStatus::Live, CompetitionStatus::BafoRound], true)
                ? AccessState::Full
                : AccessState::ReadOnly;

            return $this->access($state, $coverage, $competition, $deadline);
        }

        $unavailable = in_array($invitation->status, [InvitationStatus::Declined, InvitationStatus::Expired, InvitationStatus::Revoked], true)
            || ! in_array($competition->status, [CompetitionStatus::Scheduled, CompetitionStatus::Live], true)
            || ($deadline !== null && CarbonImmutable::now()->greaterThanOrEqualTo($deadline));

        return match (true) {
            $unavailable => $this->access(AccessState::Unavailable, Coverage::None, $competition, $deadline),
            $this->reservedPass($invitation) !== null => $this->access(AccessState::JoinRequired, Coverage::Sponsored, $competition, $deadline),
            $this->canIssue($org) => $this->access(AccessState::JoinRequired, Coverage::OwnPlan, $competition, $deadline),
            default => $this->access(AccessState::PlanRequired, Coverage::None, $competition, $deadline),
        };
    }

    public function resolveJoin(Organization $org, Invitation $invitation): EntitlementSource
    {
        $pass = SponsoredPass::query()
            ->where('invitation_id', $invitation->id)
            ->where('status', PassStatus::Reserved->value)
            ->lockForUpdate()
            ->first();

        $subscription = $org->isActive() ? $this->activeSubscription($org) : null;
        $now = CarbonImmutable::now();

        if ($subscription !== null) {
            if ($pass !== null) {
                $pass->forceFill([
                    'status' => PassStatus::Released,
                    'release_reason' => PassReleaseReason::CoveredByOwnPlan,
                    'organization_id' => $org->id,
                    'released_at' => $now,
                ])->save();

                AuditLogger::log('sponsored_pass.released', $pass, meta: ['reason' => PassReleaseReason::CoveredByOwnPlan->value]);
            }

            return $subscription->source === SubscriptionSource::Grant ? EntitlementSource::Grant : EntitlementSource::Plan;
        }

        if ($pass !== null) {
            $pass->forceFill([
                'status' => PassStatus::Joined,
                'organization_id' => $org->id,
                'joined_at' => $now,
            ])->save();

            AuditLogger::log('sponsored_pass.joined', $pass);

            return EntitlementSource::SponsoredPass;
        }

        throw new ApiException(
            'plan_required',
            'billing.errors.plan_required',
            403,
            details: ['access' => $this->participationAccess($org, $invitation)->toArray()],
        );
    }

    public function coverageFor(iterable $invitations): array
    {
        $list = [];

        foreach ($invitations as $invitation) {
            $list[] = $invitation;
        }

        if ($list === []) {
            return [];
        }

        $sponsored = SponsoredPass::query()
            ->whereIn('invitation_id', array_map(static fn (Invitation $i): int => $i->id, $list))
            ->whereIn('status', PassStatus::live())
            ->pluck('invitation_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->flip()
            ->all();

        $organizationIds = array_values(array_filter(array_map(static fn (Invitation $i): ?int => $i->organization_id, $list)));
        $coveredUntil = $this->subscriptions->coveredUntil($organizationIds);

        // One query for the competitions whose relation is not loaded yet (the list usually
        // belongs to a single competition).
        $missing = array_values(array_unique(array_map(
            static fn (Invitation $i): int => $i->competition_id,
            array_filter($list, static fn (Invitation $i): bool => ! $i->relationLoaded('competition')),
        )));
        $competitions = $missing === [] ? [] : Competition::query()->withTrashed()->whereIn('id', $missing)->get()->keyBy('id')->all();

        $coverage = [];

        foreach ($list as $invitation) {
            $competition = $invitation->relationLoaded('competition') ? $invitation->competition : $competitions[$invitation->competition_id];

            $coverage[$invitation->id] = match (true) {
                isset($sponsored[$invitation->id]) => Coverage::Sponsored,
                self::ownPlanCovers($invitation->organization_id, $competition, $coveredUntil) => Coverage::OwnPlan,
                default => Coverage::None,
            };
        }

        return $coverage;
    }

    /**
     * An organization's own plan covers a competition when its current subscription runs past
     * the invitation cutoff (or, for a draft, past the scheduled close).
     *
     * CONTRACT-GAP: a draft without a scheduled close is covered by any current subscription;
     * queued renewals extend the coverage (the last `ends_at` of the active subscriptions).
     *
     * @param  array<int, CarbonImmutable>  $coveredUntil  from SubscriptionLookup::coveredUntil()
     */
    public static function ownPlanCovers(?int $organizationId, Competition $competition, array $coveredUntil): bool
    {
        if ($organizationId === null || ! isset($coveredUntil[$organizationId])) {
            return false;
        }

        $deadline = $competition->invitation_cutoff_at ?? $competition->scheduled_close_at ?? CarbonImmutable::now();

        return $coveredUntil[$organizationId]->greaterThan($deadline);
    }

    private function reservedPass(Invitation $invitation): ?SponsoredPass
    {
        return SponsoredPass::query()
            ->where('invitation_id', $invitation->id)
            ->where('status', PassStatus::Reserved->value)
            ->first();
    }

    private function access(AccessState $state, Coverage $coverage, Competition $competition, ?CarbonImmutable $deadline): ParticipationAccess
    {
        return new ParticipationAccess(
            $state,
            $coverage,
            $coverage === Coverage::Sponsored ? $competition->organization->name : null,
            $deadline,
        );
    }
}
