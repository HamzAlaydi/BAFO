<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Enums\PaymentLineKind;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Enums\PurchaseKind;
use App\Modules\Billing\Enums\SponsorshipIntent;
use App\Modules\Billing\Enums\SponsorshipStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Events\SubscriptionActivated;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\CouponRedemption;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Billing\Models\Subscription;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;

/**
 * What a payment buys (ARCHITECTURE §13.1–§13.5). Every method runs inside
 * HandleGatewayResult's transaction, under the payment row lock, so fulfilment happens exactly
 * once, on the transition into `succeeded`.
 */
final readonly class PaymentFulfiller
{
    public function __construct(private SubscriptionLookup $subscriptions) {}

    /**
     * Fulfils a succeeded payment. False when it can no longer be fulfilled (a late
     * confirmation whose order was already abandoned): the payment then needs a manual review.
     */
    public function fulfil(Payment $payment, CarbonImmutable $now): bool
    {
        return $payment->purpose === PaymentPurpose::Subscription
            ? $this->fulfilSubscription($payment, $now)
            : $this->fulfilSponsorship($payment, $now);
    }

    /**
     * A failed or expired payment: the pending subscription is cancelled and the pending passes
     * are voided (ARCHITECTURE §6.3, §6.5).
     */
    public function abandon(Payment $payment, CarbonImmutable $now): void
    {
        Subscription::query()
            ->where('payment_id', $payment->id)
            ->where('status', SubscriptionStatus::PendingPayment->value)
            ->update(['status' => SubscriptionStatus::Cancelled->value, 'cancelled_at' => $now, 'updated_at' => $now]);

        SponsoredPass::query()
            ->where('payment_id', $payment->id)
            ->where('status', PassStatus::Pending->value)
            ->update(['status' => PassStatus::Void->value, 'voided_at' => $now, 'hold_expires_at' => null, 'updated_at' => $now]);
    }

    /**
     * Records the coupon or voucher once the payment succeeded (ARCHITECTURE §13.4). A voucher's
     * balance drops under a row lock and never below zero: the discount already charged is
     * honoured.
     */
    public function redeemCoupon(Payment $payment, CarbonImmutable $now): void
    {
        if ($payment->coupon_id === null || CouponRedemption::query()->where('payment_id', $payment->id)->exists()) {
            return;
        }

        $coupon = Coupon::query()->lockForUpdate()->find($payment->coupon_id);

        if ($coupon === null) {
            return;
        }

        CouponRedemption::query()->create([
            'coupon_id' => $coupon->id,
            'organization_id' => $payment->organization_id,
            'payment_id' => $payment->id,
            'amount_minor' => $payment->discount_minor,
            'redeemed_at' => $now,
        ]);

        $coupon->redemptions_count++;

        if ($coupon->isVoucher()) {
            $coupon->balance_minor = max(0, (int) $coupon->balance_minor - $payment->discount_minor);
        }

        $coupon->save();
    }

    private function fulfilSubscription(Payment $payment, CarbonImmutable $now): bool
    {
        $subscription = Subscription::query()
            ->where('payment_id', $payment->id)
            ->lockForUpdate()
            ->first();

        if ($subscription === null || $subscription->status !== SubscriptionStatus::PendingPayment) {
            return false;
        }

        $kind = PurchaseKind::tryFrom((string) ($payment->metadata['kind'] ?? '')) ?? PurchaseKind::New;
        $replaced = $subscription->replaces_subscription_id === null
            ? null
            : Subscription::query()->lockForUpdate()->find($subscription->replaces_subscription_id);

        if ($kind === PurchaseKind::Renewal && $replaced !== null && $replaced->ends_at !== null) {
            // A renewal queues behind the current period, which runs until its own end.
            $startsAt = $replaced->ends_at->greaterThan($now) ? $replaced->ends_at : $now;
        } else {
            // New and upgrade purchases start now and supersede whatever is current.
            $startsAt = $now;
            $current = $this->subscriptions->current($payment->organization_id, $now);

            if ($current !== null && $current->id !== $subscription->id) {
                $current->forceFill(['status' => SubscriptionStatus::Superseded, 'superseded_at' => $now])->save();
                AuditLogger::log('subscription.superseded', $current, meta: ['by' => $subscription->public_id], organizationId: $payment->organization_id);
            }
        }

        $interval = $subscription->interval;
        $endsAt = $interval === null ? $startsAt->addMonthNoOverflow() : $interval->periodEnd($startsAt);

        $subscription->forceFill([
            'status' => SubscriptionStatus::Active,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'activated_at' => $now,
        ])->save();

        AuditLogger::log('subscription.activated', $subscription, meta: [
            'source' => $subscription->source->value,
            'kind' => $kind->value,
            'payment_id' => $payment->public_id,
        ], organizationId: $payment->organization_id);

        SubscriptionActivated::dispatch($subscription);

        return true;
    }

    private function fulfilSponsorship(Payment $payment, CarbonImmutable $now): bool
    {
        $competitionId = $payment->metadata['competition_id'] ?? null;
        $sponsorship = is_numeric($competitionId)
            ? CompetitionSponsorship::query()->where('competition_id', (int) $competitionId)->lockForUpdate()->first()
            : null;

        if ($sponsorship === null || $sponsorship->status === SponsorshipStatus::Settled) {
            return false;
        }

        $quantity = (int) $payment->lines()->where('kind', PaymentLineKind::SponsoredPass->value)->sum('quantity');

        $sponsorship->forceFill([
            'funded_passes' => $sponsorship->funded_passes + $quantity,
            'status' => SponsorshipStatus::Active,
        ])->save();

        if (($payment->metadata['intent'] ?? null) === SponsorshipIntent::Publish->value) {
            SponsoredPass::query()
                ->where('payment_id', $payment->id)
                ->where('status', PassStatus::Pending->value)
                ->update(['status' => PassStatus::Reserved->value, 'reserved_at' => $now, 'hold_expires_at' => null, 'updated_at' => $now]);
        }

        AuditLogger::log('sponsorship.funded', $sponsorship, meta: [
            'passes' => $quantity,
            'payment_id' => $payment->public_id,
        ], organizationId: $payment->organization_id);

        return true;
    }
}
