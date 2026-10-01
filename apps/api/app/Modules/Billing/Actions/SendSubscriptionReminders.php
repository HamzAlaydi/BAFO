<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Events\SubscriptionExpiring;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\SubscriptionLookup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The T−7, T−3 and T−1 day reminders (ARCHITECTURE §11.3, §12
 * `billing:subscription-reminders`), each once per subscription, recorded in `reminders_sent`.
 *
 * The threshold is the smallest one at or above the days left, so a missed daily run still
 * sends the next reminder, and every larger threshold is recorded as done with it. Current
 * subscriptions that a queued renewal will continue get no reminder.
 */
final readonly class SendSubscriptionReminders
{
    /**
     * @var list<int>
     */
    public const array THRESHOLDS = [7, 3, 1];

    public function __construct(private SubscriptionLookup $subscriptions) {}

    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $ids = Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->where('ends_at', '<=', $now->addDays(max(self::THRESHOLDS)))
            ->pluck('id');

        $sent = 0;

        foreach ($ids as $id) {
            $sent += DB::transaction(function () use ($id, $now): int {
                $subscription = Subscription::query()->lockForUpdate()->find($id);

                if ($subscription === null || $subscription->ends_at === null || ! $subscription->isCurrentAt($now)
                    || $this->subscriptions->upcoming($subscription->organization_id, $now) !== null) {
                    return 0;
                }

                $daysLeft = (int) ceil($now->diffInSeconds($subscription->ends_at, false) / 86_400);
                $threshold = $this->threshold($daysLeft);
                $done = array_map('intval', $subscription->reminders_sent);

                if ($threshold === null || in_array($threshold, $done, true)) {
                    return 0;
                }

                $subscription->reminders_sent = array_values(array_unique([
                    ...$done,
                    ...array_filter(self::THRESHOLDS, static fn (int $t): bool => $t >= $threshold),
                ]));
                $subscription->save();

                SubscriptionExpiring::dispatch($subscription, max(1, $daysLeft));

                return 1;
            });
        }

        return $sent;
    }

    private function threshold(int $daysLeft): ?int
    {
        $candidates = array_filter(self::THRESHOLDS, static fn (int $t): bool => $daysLeft <= $t);

        return $candidates === [] ? null : min($candidates);
    }
}
