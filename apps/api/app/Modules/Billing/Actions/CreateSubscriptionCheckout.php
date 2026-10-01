<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Data\SubscriptionCheckoutInput;
use App\Modules\Billing\Enums\PaymentLineKind;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\BillingSettings;
use App\Modules\Billing\Services\CheckoutGuard;
use App\Modules\Billing\Services\CouponValidator;
use App\Modules\Billing\Services\Gateways\PaymentGatewayManager;
use App\Modules\Billing\Services\LineDescriptions;
use App\Modules\Billing\Services\PricingCalculator;
use App\Modules\Billing\Services\SubscriptionPurchaseResolver;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * `POST /billing/checkout/subscription` (API.md §1.7, ARCHITECTURE §13.1–§13.2).
 *
 * The permission (`billing.purchase`) is checked by the route. Then: the platform, the billing
 * profile and the return URL (CheckoutGuard); an `Idempotency-Key` replay returns the same
 * payment; the purchase is priced (new, renewal or upgrade, coupon, VAT). One transaction
 * inserts the pending payment with its line and a `pending_payment` subscription carrying the
 * amounts; after the commit the gateway checkout opens (or a zero total is fulfilled at once).
 */
final readonly class CreateSubscriptionCheckout
{
    public function __construct(
        private CheckoutGuard $guard,
        private SubscriptionPurchaseResolver $purchases,
        private CouponValidator $coupons,
        private PricingCalculator $pricing,
        private BillingSettings $settings,
        private PaymentGatewayManager $gateways,
        private OpenGatewayCheckout $openCheckout,
    ) {}

    public function handle(User $payer, Organization $organization, SubscriptionCheckoutInput $input, Actor $actor): Payment
    {
        $this->guard->check($actor, $organization, $input->returnUrl);

        if ($input->idempotencyKey !== null) {
            $existing = Payment::query()
                ->where('organization_id', $organization->id)
                ->where('idempotency_key', $input->idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $plan = Plan::query()->wherePublicId(strtolower($input->planId))->first()
            ?? throw new ApiException('plan_not_available', 'billing.errors.plan_not_available', 422);

        $now = CarbonImmutable::now();
        $purchase = $this->purchases->resolve($organization->id, $plan, $input->interval, $input->seats, $now);
        $coupon = $input->couponCode === null || trim($input->couponCode) === ''
            ? null
            : $this->coupons->validate($input->couponCode, $organization->id, PaymentPurpose::Subscription, $now);
        $price = $this->pricing->breakdown($purchase->subtotalMinor(), $purchase->creditMinor, $coupon, $this->settings->vatRateBp());

        $payment = DB::transaction(function () use ($payer, $organization, $input, $plan, $purchase, $coupon, $price, $now, $actor): Payment {
            $payment = Payment::query()->create([
                'organization_id' => $organization->id,
                'created_by_user_id' => $payer->id,
                'purpose' => PaymentPurpose::Subscription,
                'status' => PaymentStatus::Pending,
                'gateway' => $this->gateways->defaultName(),
                'currency' => 'SAR',
                'subtotal_minor' => $price->subtotalMinor,
                'discount_minor' => $price->discountMinor,
                'credit_minor' => $price->creditMinor,
                'vat_rate_bp' => $price->vatRateBp,
                'vat_minor' => $price->vatMinor,
                'total_minor' => $price->totalMinor,
                'coupon_id' => $coupon?->id,
                'idempotency_key' => $input->idempotencyKey,
                'return_url' => $input->returnUrl,
                'metadata' => [
                    'kind' => $purchase->kind->value,
                    'plan_code' => $plan->code,
                    'interval' => $purchase->interval->value,
                    'seats' => $purchase->seats,
                ],
                'expires_at' => $now->addMinutes($this->settings->checkoutHoldMinutes()),
            ]);

            $subscription = Subscription::query()->create([
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
                'source' => SubscriptionSource::Paid,
                'interval' => $purchase->interval,
                'seats' => $purchase->seats,
                'status' => SubscriptionStatus::PendingPayment,
                'unit_price_minor' => $purchase->unitPriceMinor,
                'subtotal_minor' => $price->subtotalMinor,
                'discount_minor' => $price->discountMinor,
                'credit_minor' => $price->creditMinor,
                'vat_minor' => $price->vatMinor,
                'total_minor' => $price->totalMinor,
                'payment_id' => $payment->id,
                'replaces_subscription_id' => $purchase->replaces?->id,
            ]);

            $payment->lines()->create([
                'kind' => $plan->is_custom ? PaymentLineKind::CustomSeats : PaymentLineKind::Plan,
                'description' => LineDescriptions::plan($plan, $purchase->interval, $purchase->seats),
                'quantity' => $purchase->quantity,
                'unit_price_minor' => $purchase->unitPriceMinor,
                'net_minor' => $purchase->subtotalMinor(),
                'ref_type' => $subscription->getMorphClass(),
                'ref_id' => $subscription->id,
            ]);

            $payment->metadata = [...$payment->metadata, 'subscription_id' => $subscription->id];
            $payment->save();

            AuditLogger::log('payment.created', $payment, meta: [
                'purpose' => PaymentPurpose::Subscription->value,
                'kind' => $purchase->kind->value,
                'plan_code' => $plan->code,
                'total_minor' => $price->totalMinor,
            ], actor: $actor);

            return $payment;
        });

        return $this->openCheckout->handle($payment, $actor);
    }
}
