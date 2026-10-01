<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Billing\Models\Payment;
use App\Modules\Notifications\Services\BillingNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `payment.failed` on `App\Modules\Billing\Events\PaymentFailed` (ARCHITECTURE §10, §11.3).
 */
final class NotifyPaymentFailed extends QueuedNotificationListener
{
    public function __construct(private readonly BillingNotifier $notifier) {}

    /**
     * @param  object  $event  PaymentFailed
     */
    public function handle(object $event): void
    {
        $this->notifier->paymentFailed(EventPayload::instance($event, 'payment', Payment::class));
    }
}
