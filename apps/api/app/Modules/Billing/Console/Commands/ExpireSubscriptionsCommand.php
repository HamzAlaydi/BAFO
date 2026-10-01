<?php

declare(strict_types=1);

namespace App\Modules\Billing\Console\Commands;

use App\Modules\Billing\Actions\ExpireSubscriptions;
use App\Support\Auth\Actor;
use Illuminate\Console\Command;

/**
 * `billing:expire-subscriptions` (every five minutes, ARCHITECTURE §12): active subscriptions
 * with `ends_at ≤ now` → expired, plus SubscriptionExpired.
 */
final class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'billing:expire-subscriptions';

    protected $description = 'Expire subscriptions whose period has ended';

    public function handle(ExpireSubscriptions $expire): int
    {
        $count = $expire->handle(Actor::system());

        $this->components->info("Expired {$count} subscriptions.");

        return self::SUCCESS;
    }
}
