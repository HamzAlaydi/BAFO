<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Notifications\Services\BillingNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `invoice.issued` on `App\Modules\Billing\Events\InvoiceIssued` (ARCHITECTURE §10, §11.3).
 */
final class NotifyInvoiceIssued extends QueuedNotificationListener
{
    public function __construct(private readonly BillingNotifier $notifier) {}

    /**
     * @param  object  $event  InvoiceIssued
     */
    public function handle(object $event): void
    {
        $this->notifier->invoiceIssued(EventPayload::instance($event, 'invoice', Invoice::class));
    }
}
