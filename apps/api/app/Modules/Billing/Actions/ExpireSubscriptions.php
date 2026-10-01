<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Events\SubscriptionExpired;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\SubscriptionLookup;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * `active → expired` for subscriptions with `ends_at ≤ now` (ARCHITECTURE §6.5, §12
 * `billing:expire-subscriptions`). Each row is locked and re-checked, so overlapping runs are
 * harmless.
 *
 * CONTRACT-GAP: SubscriptionExpired (the "your plan has expired" notification) is dispatched
 * only when no other subscription of the organization is current, so a queued renewal that
 * takes over does not announce an expiry.
 */
final readonly class ExpireSubscriptions
{
    public function __construct(private SubscriptionLookup $subscriptions) {}

    public function handle(Actor $actor, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $ids = Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->where('ends_at', '<=', $now)
            ->orderBy('ends_at')
            ->pluck('id');

        $expired = 0;

        foreach ($ids as $id) {
            $expired += DB::transaction(function () use ($id, $now, $actor): int {
                $subscription = Subscription::query()->lockForUpdate()->find($id);

                if ($subscription === null || $subscription->status !== SubscriptionStatus::Active
                    || $subscription->ends_at === null || $subscription->ends_at->greaterThan($now)) {
                    return 0;
                }

                $subscription->forceFill(['status' => SubscriptionStatus::Expired, 'expired_at' => $now])->save();

                AuditLogger::log('subscription.expired', $subscription, [
                    'status' => ['from' => SubscriptionStatus::Active->value, 'to' => SubscriptionStatus::Expired->value],
                ], actor: $actor, organizationId: $subscription->organization_id);

                if ($this->subscriptions->current($subscription->organization_id, $now) === null) {
                    SubscriptionExpired::dispatch($subscription);
                }

                return 1;
            });
        }

        return $expired;
    }
}
