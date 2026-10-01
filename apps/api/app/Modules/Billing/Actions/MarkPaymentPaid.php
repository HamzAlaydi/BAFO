<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Data\GatewayPaymentState;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Models\Payment;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Validation\ValidationException;

/**
 * Admin "Mark paid manually" (ARCHITECTURE §16, e.g. a bank transfer): HandleGatewayResult
 * with a manual paid state for the exact total and the admin's reference. Allowed for pending
 * and expired payments (the late confirmation path).
 */
final readonly class MarkPaymentPaid
{
    public function __construct(private HandleGatewayResult $handleResult) {}

    public function handle(Payment $payment, string $reference, Actor $actor): Payment
    {
        $reference = trim($reference);

        if ($reference === '') {
            $message = __('billing.validation.reference_required');

            throw ValidationException::withMessages(['reference' => [is_string($message) ? $message : 'reference']]);
        }

        if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Expired], true)) {
            throw new ApiException('invalid_state_transition', status: 409, details: ['from' => $payment->status->value, 'to' => PaymentStatus::Succeeded->value]);
        }

        return $this->handleResult->handle(
            $payment,
            GatewayPaymentState::paid($payment->total_minor, $payment->currency),
            $actor,
            mb_substr($reference, 0, 120),
        );
    }
}
