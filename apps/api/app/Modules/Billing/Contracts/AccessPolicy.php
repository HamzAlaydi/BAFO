<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Data\ParticipationAccess;
use App\Modules\Billing\Enums\Coverage;
use App\Modules\Billing\Enums\EntitlementSource;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Models\Organization;
use App\Support\Exceptions\ApiException;

/**
 * The single entitlement policy (ARCHITECTURE §3.6, §8.4). It replaces the legacy
 * `has_valid_subscription`. Bound as a singleton by BillingServiceProvider.
 */
interface AccessPolicy
{
    /**
     * An active organization with a current subscription (paid, trial or grant) now.
     */
    public function canIssue(Organization $org): bool;

    /**
     * The current subscription: `status = active AND starts_at <= now < ends_at`.
     */
    public function activeSubscription(Organization $org): ?Subscription;

    /**
     * Plan seats, or 1 without a plan.
     */
    public function seatLimit(Organization $org): int;

    /**
     * Access of an invitee organization to a competition it was invited to (§8.4).
     */
    public function participationAccess(Organization $org, Invitation $invitation): ParticipationAccess;

    /**
     * Called INSIDE the join transaction. Consumes (or releases) the invitation's reserved pass.
     *
     * @throws ApiException plan_required (403, `details.access`)
     */
    public function resolveJoin(Organization $org, Invitation $invitation): EntitlementSource;

    /**
     * @param  iterable<Invitation>  $invitations
     * @return array<int, Coverage> keyed by invitation id
     */
    public function coverageFor(iterable $invitations): array;
}
