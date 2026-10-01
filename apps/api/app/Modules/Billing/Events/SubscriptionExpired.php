<?php

declare(strict_types=1);

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A subscription reached its end and no other one is current (ARCHITECTURE §10). Listener: Notifications `subscription.expired`.
 */
final readonly class SubscriptionExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(public Subscription $subscription) {}
}
