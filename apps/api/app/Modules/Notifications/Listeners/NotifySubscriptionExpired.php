<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Notifications\Services\BillingNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `subscription.expired` on `App\Modules\Billing\Events\SubscriptionExpired` (ARCHITECTURE §10, §11.3).
 */
final class NotifySubscriptionExpired extends QueuedNotificationListener
{
    public function __construct(private readonly BillingNotifier $notifier) {}

    /**
     * @param  object  $event  SubscriptionExpired
     */
    public function handle(object $event): void
    {
        $this->notifier->subscriptionExpired(EventPayload::instance($event, 'subscription', Subscription::class));
    }
}
