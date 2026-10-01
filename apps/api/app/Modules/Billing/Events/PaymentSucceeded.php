<?php

declare(strict_types=1);

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A payment reached `succeeded` and was fulfilled (ARCHITECTURE §10). Listener: Billing q[billing] IssueInvoiceForPayment.
 */
final readonly class PaymentSucceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(public Payment $payment) {}
}
