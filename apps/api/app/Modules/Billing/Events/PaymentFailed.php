<?php

declare(strict_types=1);

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A payment was declined, cancelled on the hosted page or failed the amount check (ARCHITECTURE §10). Listener: Notifications `payment.failed`.
 */
final readonly class PaymentFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(public Payment $payment) {}
}
