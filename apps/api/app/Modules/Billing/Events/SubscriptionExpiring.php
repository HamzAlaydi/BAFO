<?php

declare(strict_types=1);

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A current subscription ends in 7, 3 or 1 days (ARCHITECTURE §10, §12). Listener: Notifications `subscription.expiring`.
 */
final readonly class SubscriptionExpiring
{
    use Dispatchable, SerializesModels;

    public function __construct(public Subscription $subscription, public int $daysLeft) {}
}
