<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Models\Organization;

/**
 * What Identity needs from Billing: seats, the current subscription and the issuer entitlement
 * (`/me`, `GET /organization`, team seat checks, ARCHITECTURE §8.4 and §13.2). Every entitlement
 * answer comes from Billing's `AccessPolicy` contract (§3.6).
 */
final readonly class Entitlements
{
    public function __construct(private AccessPolicy $accessPolicy) {}

    /**
     * `AccessPolicy::seatLimit()`: the plan seats, or 1 without a plan.
     */
    public function seatLimit(Organization $organization): int
    {
        return $this->accessPolicy->seatLimit($organization);
    }

    /**
     * Seat usage (§13.2): memberships with status `invited` or `active`.
     */
    public function seatsUsed(Organization $organization): int
    {
        return $organization->memberships()
            ->whereIn('status', [MembershipStatus::Invited->value, MembershipStatus::Active->value])
            ->count();
    }

    /**
     * `AccessPolicy::canIssue()`: the organization is active and has a current subscription.
     */
    public function canIssue(Organization $organization): bool
    {
        return $this->accessPolicy->canIssue($organization);
    }

    /**
     * `AccessPolicy::activeSubscription()`: `status = active AND starts_at <= now < ends_at`.
     */
    public function currentSubscription(Organization $organization): ?Subscription
    {
        return $this->accessPolicy->activeSubscription($organization);
    }

    /**
     * The trial rule of §13.3, the same as Billing's `StartTrial`: `trial_used_at` is null, the
     * organization never had an activated paid subscription, and no subscription is current.
     *
     * CONTRACT-GAP: `trial_available` (API.md §2.2) has no AccessPolicy method. The rule mirrors
     * `StartTrial` (and `GET /billing/subscription`), so the flag is true only when the trial can
     * actually be started.
     */
    public function trialAvailable(Organization $organization): bool
    {
        if ($organization->trial_used_at !== null) {
            return false;
        }

        $hasPaidHistory = Subscription::query()
            ->where('organization_id', $organization->id)
            ->where('source', SubscriptionSource::Paid->value)
            ->whereNotNull('activated_at')
            ->exists();

        return ! $hasPaidHistory && $this->currentSubscription($organization) === null;
    }
}
