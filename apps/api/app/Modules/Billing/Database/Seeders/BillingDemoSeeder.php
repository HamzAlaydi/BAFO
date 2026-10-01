<?php

declare(strict_types=1);

namespace App\Modules\Billing\Database\Seeders;

use App\Modules\Billing\Actions\CreateSubscriptionCheckout;
use App\Modules\Billing\Actions\HandleGatewayResult;
use App\Modules\Billing\Actions\InvoicePayment;
use App\Modules\Billing\Actions\StartTrial;
use App\Modules\Billing\Data\GatewayPaymentState;
use App\Modules\Billing\Data\SubscriptionCheckoutInput;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\CouponKind;
use App\Modules\Billing\Enums\CouponScope;
use App\Modules\Billing\Enums\DiscountType;
use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Auth\Actor;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Billing demo data (ARCHITECTURE §17, docs/build/DEMO.md): each demo organization gets the
 * subscription of its `plan` column through the real flows:
 *
 * - `trial` → StartTrial;
 * - a paid plan → a monthly checkout by the owner, approved on the fake gateway, with its tax
 *   invoice (and PDF);
 * - null → no subscription.
 *
 * It also creates the public demo coupon `LAUNCH10` (10% on any purchase). Organizations are
 * found by CR number; one that Identity has not seeded is skipped with a warning.
 */
final class BillingDemoSeeder extends Seeder
{
    public const string DEMO_COUPON = 'LAUNCH10';

    public function run(
        StartTrial $startTrial,
        CreateSubscriptionCheckout $checkout,
        HandleGatewayResult $handleResult,
        InvoicePayment $invoicePayment,
    ): void {
        $this->call(BillingReferenceSeeder::class);

        Coupon::query()->firstOrCreate(['code' => self::DEMO_COUPON], [
            'kind' => CouponKind::Coupon,
            'discount_type' => DiscountType::Percent,
            'percent_bps' => 1000,
            'applies_to' => CouponScope::Any,
            'per_organization_limit' => 1,
            'valid_from' => CarbonImmutable::now()->subDay(),
            'valid_until' => CarbonImmutable::now()->addYear(),
            'is_active' => true,
            'reason' => 'Demo launch coupon',
        ]);

        foreach (DemoSeeder::ORGANIZATIONS as $key => $definition) {
            $planCode = $definition['plan'];

            if ($planCode === null) {
                continue;
            }

            $organization = Organization::query()->where('cr_number', $definition['cr_number'])->first();

            if ($organization === null) {
                Log::warning('BillingDemoSeeder: organization not found (run IdentityDemoSeeder first); skipped.', ['organization' => $key]);

                continue;
            }

            if ($planCode === 'trial') {
                $startTrial->handle($organization, Actor::system());

                continue;
            }

            $plan = Plan::query()->where('code', $planCode)->firstOrFail();
            $owner = User::query()->where('email', DemoSeeder::user("{$key}.owner")['email'])->first();

            if ($owner === null || ! $organization->isBillingProfileComplete()) {
                $this->grantPaidPeriod($organization, $plan);

                continue;
            }

            $actor = Actor::forUser($owner);
            $payment = $checkout->handle($owner, $organization, new SubscriptionCheckoutInput(
                planId: $plan->public_id,
                interval: BillingInterval::Monthly,
                seats: null,
                couponCode: null,
                returnUrl: $this->returnUrl(),
            ), $actor);

            $payment = $handleResult->handle($payment, GatewayPaymentState::paid($payment->total_minor, $payment->currency), $actor);
            $invoicePayment->handle($payment, Actor::system());
        }
    }

    /**
     * Fallback when the organization cannot check out (no owner or an incomplete billing
     * profile): the same paid monthly period, without a payment.
     */
    private function grantPaidPeriod(Organization $organization, Plan $plan): void
    {
        $now = CarbonImmutable::now();
        $unit = (int) $plan->monthly_price_minor;
        $vat = Money::vat($unit);

        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'source' => SubscriptionSource::Paid,
            'interval' => BillingInterval::Monthly,
            'seats' => $plan->seats ?? 1,
            'status' => SubscriptionStatus::Active,
            'starts_at' => $now,
            'ends_at' => $now->addMonthNoOverflow(),
            'unit_price_minor' => $unit,
            'subtotal_minor' => $unit,
            'discount_minor' => 0,
            'credit_minor' => 0,
            'vat_minor' => $vat,
            'total_minor' => $unit + $vat,
            'activated_at' => $now,
        ]);
    }

    private function returnUrl(): string
    {
        $allowed = (array) config('bafo.billing.allowed_return_urls', []);
        $origin = is_string($allowed[0] ?? null) ? rtrim($allowed[0], '/') : 'http://localhost:3000';

        return $origin.'/ar/dashboard/billing/checkout/return';
    }
}
