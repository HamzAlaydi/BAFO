<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\GatewayPaymentStatus;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Services\Gateways\PaymentGatewayManager;
use App\Modules\Billing\Services\PaymentFulfiller;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Asks the gateway about one payment and applies the answer (ARCHITECTURE §12
 * `billing:reconcile-payments`, §16 "Reconcile now", and `POST …/payments/{id}/verify`):
 *
 * - paid or failed → HandleGatewayResult;
 * - still not paid after `expires_at` → `expired` (`failure_code = expired`), with the pending
 *   subscription cancelled and the pending passes voided;
 * - an expired payment the gateway now reports paid → the late confirmation.
 *
 * Any other settled payment is returned untouched, without a gateway call.
 */
final readonly class ReconcilePayment
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private HandleGatewayResult $handleResult,
        private PaymentFulfiller $fulfiller,
    ) {}

    public function handle(Payment $payment, Actor $actor): Payment
    {
        if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Expired], true)) {
            return $payment;
        }

        $state = $this->gateways->for($payment)->fetch($payment);

        // An expired payment only changes on a late confirmation (§6.4 `expired → succeeded`).
        if ($payment->status === PaymentStatus::Expired) {
            return $state->status === GatewayPaymentStatus::Paid ? $this->handleResult->handle($payment, $state, $actor) : $payment;
        }

        if ($state->status !== GatewayPaymentStatus::Pending) {
            return $this->handleResult->handle($payment, $state, $actor);
        }

        if ($payment->expires_at->greaterThan(CarbonImmutable::now())) {
            return $payment;
        }

        return $this->expire($payment, $actor);
    }

    private function expire(Payment $payment, Actor $actor): Payment
    {
        return DB::transaction(function () use ($payment, $actor): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status !== PaymentStatus::Pending) {
                return $locked;
            }

            $now = CarbonImmutable::now();
            $locked->forceFill(['status' => PaymentStatus::Expired, 'failure_code' => 'expired'])->save();
            $this->fulfiller->abandon($locked, $now);

            AuditLogger::log('payment.expired', $locked, [
                'status' => ['from' => PaymentStatus::Pending->value, 'to' => PaymentStatus::Expired->value],
            ], actor: $actor, organizationId: $locked->organization_id);

            return $locked;
        });
    }
}
