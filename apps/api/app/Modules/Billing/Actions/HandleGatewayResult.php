<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Data\GatewayPaymentState;
use App\Modules\Billing\Enums\GatewayPaymentStatus;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Events\PaymentFailed;
use App\Modules\Billing\Events\PaymentSucceeded;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Services\PaymentFulfiller;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Auth\CurrentActor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The single place a payment's outcome lands (ARCHITECTURE §13.1): the gateway webhook, the
 * verify endpoint, the fake page, the reconciler and the admin all call it. Under
 * `SELECT … FOR UPDATE` on the payment:
 *
 * - a terminal payment does not change (except the late `expired → succeeded`);
 * - paid, with the exact amount and currency → `succeeded`, fulfil, redeem the coupon,
 *   PaymentSucceeded; any other amount → `failed` (`amount_mismatch`);
 * - failed → `failed`, the pending subscription is cancelled and the pending passes voided,
 *   PaymentFailed.
 *
 * After the commit, a fulfilled sponsorship payment publishes the competition or sends the
 * invitations it paid for (CompleteSponsorshipIntent).
 */
final readonly class HandleGatewayResult
{
    public function __construct(
        private PaymentFulfiller $fulfiller,
        private CompleteSponsorshipIntent $completeSponsorship,
    ) {}

    public function handle(Payment $payment, GatewayPaymentState $state, ?Actor $actor = null, ?string $manualReference = null): Payment
    {
        $actor ??= CurrentActor::get();

        /** @var array{0: Payment, 1: bool} $result */
        $result = DB::transaction(function () use ($payment, $state, $actor, $manualReference): array {
            /** @var Payment $locked */
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $now = CarbonImmutable::now();
            $late = $locked->status === PaymentStatus::Expired;

            if ($state->status === GatewayPaymentStatus::Pending
                || $locked->status->isTerminal()
                || ($late && $state->status !== GatewayPaymentStatus::Paid)) {
                return [$locked, false];
            }

            if ($state->status === GatewayPaymentStatus::Paid) {
                if ($state->amountMinor !== $locked->total_minor || strtoupper((string) $state->currency) !== $locked->currency) {
                    $this->fail($locked, 'amount_mismatch', null, $now, $actor);

                    return [$locked, false];
                }

                return [$locked, $this->succeed($locked, $now, $actor, $manualReference, $late)];
            }

            $this->fail($locked, $state->failureCode ?? 'declined', $state->failureMessage, $now, $actor);

            return [$locked, false];
        });

        [$payment, $fulfilled] = $result;

        if ($fulfilled && $payment->purpose === PaymentPurpose::Sponsorship) {
            $this->completeSponsorship->handle($payment);
        }

        return $payment->refresh();
    }

    private function succeed(Payment $payment, CarbonImmutable $now, Actor $actor, ?string $manualReference, bool $late): bool
    {
        $from = $payment->status;
        $payment->forceFill([
            'status' => PaymentStatus::Succeeded,
            'paid_at' => $now,
            'failure_code' => null,
            'failure_message' => null,
            'manual_reference' => $manualReference ?? $payment->manual_reference,
        ]);

        $fulfilled = $this->fulfiller->fulfil($payment, $now);

        if (! $fulfilled) {
            // A late confirmation whose order was already abandoned: keep the money on record and
            // let the admins resolve it (ARCHITECTURE §6.4 `expired → succeeded`).
            $payment->metadata = [...$payment->metadata, 'needs_manual_review' => true];
            Log::warning('Payment succeeded but could not be fulfilled; manual review needed.', [
                'payment_id' => $payment->public_id,
                'late' => $late,
            ]);
        }

        $payment->save();
        $this->fulfiller->redeemCoupon($payment, $now);

        AuditLogger::log('payment.succeeded', $payment, ['status' => ['from' => $from->value, 'to' => PaymentStatus::Succeeded->value]], [
            'total_minor' => $payment->total_minor,
            'late' => $late,
            'manual_reference' => $manualReference,
        ], $actor, $payment->organization_id);

        PaymentSucceeded::dispatch($payment);

        return $fulfilled;
    }

    private function fail(Payment $payment, string $code, ?string $message, CarbonImmutable $now, Actor $actor): void
    {
        $from = $payment->status;
        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'failed_at' => $now,
            'failure_code' => mb_substr($code, 0, 60),
            'failure_message' => $message === null ? null : mb_substr($message, 0, 500),
        ])->save();

        $this->fulfiller->abandon($payment, $now);

        AuditLogger::log('payment.failed', $payment, ['status' => ['from' => $from->value, 'to' => PaymentStatus::Failed->value]], [
            'failure_code' => $code,
        ], $actor, $payment->organization_id);

        PaymentFailed::dispatch($payment);
    }
}
