<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Notifications\Services\BillingNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `subscription.expiring` on `App\Modules\Billing\Events\SubscriptionExpiring` (ARCHITECTURE §10, §11.3).
 */
final class NotifySubscriptionExpiring extends QueuedNotificationListener
{
    public function __construct(private readonly BillingNotifier $notifier) {}

    /**
     * @param  object  $event  SubscriptionExpiring
     */
    public function handle(object $event): void
    {
        $this->notifier->subscriptionExpiring(
            EventPayload::instance($event, 'subscription', Subscription::class),
            EventPayload::int($event, 'daysLeft'),
        );
    }
}
