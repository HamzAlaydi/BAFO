<?php

declare(strict_types=1);

namespace App\Modules\Billing\Console\Commands;

use App\Modules\Billing\Actions\SendSubscriptionReminders;
use Illuminate\Console\Command;

/**
 * `billing:subscription-reminders` (daily at 06:00 UTC = 09:00 Riyadh, ARCHITECTURE §12): the
 * T−7, T−3 and T−1 SubscriptionExpiring events, once each.
 */
final class SubscriptionRemindersCommand extends Command
{
    protected $signature = 'billing:subscription-reminders';

    protected $description = 'Announce subscriptions that end in 7, 3 or 1 days';

    public function handle(SendSubscriptionReminders $reminders): int
    {
        $count = $reminders->handle();

        $this->components->info("Sent {$count} subscription reminders.");

        return self::SUCCESS;
    }
}
