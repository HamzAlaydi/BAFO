<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Notifications\Services\BillingNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `subscription.activated` on `App\Modules\Billing\Events\SubscriptionActivated` (ARCHITECTURE §10, §11.3).
 */
final class NotifySubscriptionActivated extends QueuedNotificationListener
{
    public function __construct(private readonly BillingNotifier $notifier) {}

    /**
     * @param  object  $event  SubscriptionActivated
     */
    public function handle(object $event): void
    {
        $this->notifier->subscriptionActivated(EventPayload::instance($event, 'subscription', Subscription::class));
    }
}
