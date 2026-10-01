<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Models\Membership;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read queries over subscriptions (ARCHITECTURE §5.7):
 *
 * - "current"  = `status = active AND starts_at <= now < ends_at` (the latest start wins);
 * - "upcoming" = `status = active AND starts_at > now` (a queued renewal).
 */
final class SubscriptionLookup
{
    public function current(int $organizationId, ?CarbonImmutable $at = null): ?Subscription
    {
        $at ??= CarbonImmutable::now();

        return $this->active($organizationId)
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>', $at)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
    }

    public function upcoming(int $organizationId, ?CarbonImmutable $at = null): ?Subscription
    {
        $at ??= CarbonImmutable::now();

        return $this->active($organizationId)
            ->where('starts_at', '>', $at)
            ->orderBy('starts_at')
            ->orderBy('id')
            ->first();
    }

    /**
     * Whether the organization ever had an activated paid subscription (the trial rule, §13.3).
     */
    public function hasPaidHistory(int $organizationId): bool
    {
        return Subscription::query()
            ->where('organization_id', $organizationId)
            ->where('source', SubscriptionSource::Paid->value)
            ->whereNotNull('activated_at')
            ->exists();
    }

    /**
     * Seat usage: memberships with status invited or active (ARCHITECTURE §13.2 "Seats").
     */
    public function seatsUsed(int $organizationId): int
    {
        return Membership::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', [MembershipStatus::Invited->value, MembershipStatus::Active->value])
            ->count();
    }

    /**
     * For each organization with a current subscription, the last `ends_at` of its active
     * subscriptions (current and queued renewals): how long its own plan covers it.
     *
     * @param  list<int>  $organizationIds
     * @return array<int, CarbonImmutable>
     */
    public function coveredUntil(array $organizationIds, ?CarbonImmutable $at = null): array
    {
        $organizationIds = array_values(array_unique($organizationIds));

        if ($organizationIds === []) {
            return [];
        }

        $at ??= CarbonImmutable::now();

        $current = Subscription::query()
            ->whereIn('organization_id', $organizationIds)
            ->where('status', SubscriptionStatus::Active->value)
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>', $at)
            ->distinct()
            ->pluck('organization_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($current === []) {
            return [];
        }

        $until = [];

        Subscription::query()
            ->whereIn('organization_id', $current)
            ->where('status', SubscriptionStatus::Active->value)
            ->where('ends_at', '>', $at)
            ->get(['organization_id', 'ends_at'])
            ->each(static function (Subscription $subscription) use (&$until): void {
                $endsAt = $subscription->ends_at;
                $organizationId = $subscription->organization_id;

                if ($endsAt !== null && (! isset($until[$organizationId]) || $endsAt->greaterThan($until[$organizationId]))) {
                    $until[$organizationId] = $endsAt;
                }
            });

        return $until;
    }

    /**
     * @return Builder<Subscription>
     */
    private function active(int $organizationId): Builder
    {
        return Subscription::query()
            ->where('organization_id', $organizationId)
            ->where('status', SubscriptionStatus::Active->value);
    }
}
