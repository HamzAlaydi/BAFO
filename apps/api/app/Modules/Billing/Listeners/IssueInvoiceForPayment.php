<?php

declare(strict_types=1);

namespace App\Modules\Billing\Listeners;

use App\Modules\Billing\Actions\InvoicePayment;
use App\Modules\Billing\Events\PaymentSucceeded;
use App\Support\Auth\Actor;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * PaymentSucceeded → the tax invoice and its e-invoice (ARCHITECTURE §10, §13.7). Queued on
 * `billing` after the commit; idempotent (one invoice per payment).
 */
final class IssueInvoiceForPayment implements ShouldHandleEventsAfterCommit, ShouldQueueAfterCommit
{
    public string $queue = 'billing';

    public int $tries = 3;

    public function __construct(private readonly InvoicePayment $invoicePayment) {}

    public function handle(PaymentSucceeded $event): void
    {
        $this->invoicePayment->handle($event->payment, Actor::system());
    }
}
