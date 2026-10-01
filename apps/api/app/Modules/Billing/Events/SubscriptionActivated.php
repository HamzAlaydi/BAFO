<?php

declare(strict_types=1);

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A subscription became active: paid, trial or grant (ARCHITECTURE §10). Listener: Notifications `subscription.activated`.
 */
final readonly class SubscriptionActivated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Subscription $subscription) {}
}
