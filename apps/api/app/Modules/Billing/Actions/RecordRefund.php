<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Models\Payment;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin "Record refund" (ARCHITECTURE §6.4 `succeeded → refunded`, §16): the refund itself is
 * done in the gateway dashboard; this records its reference. Refund automation and automatic
 * credit notes are out of the MVP scope (§18).
 */
final class RecordRefund
{
    public function handle(Payment $payment, string $reference, Actor $actor): Payment
    {
        $reference = trim($reference);

        if ($reference === '') {
            $message = __('billing.validation.reference_required');

            throw ValidationException::withMessages(['reference' => [is_string($message) ? $message : 'reference']]);
        }

        return DB::transaction(static function () use ($payment, $reference, $actor): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status !== PaymentStatus::Succeeded) {
                throw new ApiException('invalid_state_transition', status: 409, details: ['from' => $locked->status->value, 'to' => PaymentStatus::Refunded->value]);
            }

            $locked->forceFill([
                'status' => PaymentStatus::Refunded,
                'refunded_at' => CarbonImmutable::now(),
                'refund_reference' => mb_substr($reference, 0, 120),
                'refunded_by_admin_id' => $actor->adminId,
            ])->save();

            AuditLogger::log('payment.refunded', $locked, [
                'status' => ['from' => PaymentStatus::Succeeded->value, 'to' => PaymentStatus::Refunded->value],
            ], ['refund_reference' => $locked->refund_reference], $actor, $locked->organization_id);

            return $locked;
        });
    }
}
