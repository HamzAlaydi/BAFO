<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Billing\Models\Coupon;
use App\Modules\Notifications\Services\BillingNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `voucher.issued` on `App\Modules\Billing\Events\VoucherIssued` (ARCHITECTURE §10, §11.3).
 */
final class NotifyVoucherIssued extends QueuedNotificationListener
{
    public function __construct(private readonly BillingNotifier $notifier) {}

    /**
     * @param  object  $event  VoucherIssued
     */
    public function handle(object $event): void
    {
        $this->notifier->voucherIssued(EventPayload::instance($event, 'voucher', Coupon::class));
    }
}
