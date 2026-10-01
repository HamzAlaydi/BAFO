<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\CouponKind;
use App\Modules\Billing\Enums\DiscountType;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\PricingCalculator;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

/*
| The checkout math of ARCHITECTURE §13.2, in integer halalas.
*/

function billingPricingCoupon(array $attributes): Coupon
{
    $coupon = new Coupon;
    $coupon->forceFill([
        'kind' => CouponKind::Coupon,
        'discount_type' => DiscountType::Percent,
        ...$attributes,
    ]);

    return $coupon;
}

it('adds 15% VAT rounded half up', function (int $net, int $vat) {
    $price = (new PricingCalculator)->breakdown($net, 0, null, 1500);

    expect($price->vatMinor)->toBe($vat)
        ->and($price->totalMinor)->toBe($net + $vat)
        ->and($price->vatMinor)->toBe(Money::vat($net));
})->with([
    'whole riyals' => [150_000, 22_500],
    'SAR 315' => [31_500, 4_725],
    'exactly half a halala rounds up (10 → 1.5)' => [10, 2],
    'below half rounds down (3 → 0.45)' => [3, 0],
    'above half rounds up (7 → 1.05 → 1)' => [7, 1],
    '0.5 halala boundary (30 → 4.5)' => [30, 5],
    'zero' => [0, 0],
]);

it('applies a percent coupon with floor on subtotal minus credit', function () {
    $price = (new PricingCalculator)->breakdown(150_000, 30_001, billingPricingCoupon(['percent_bps' => 1000]), 1500);

    // (150000 − 30001) × 10% = 11999.9 → floor 11999
    expect($price->creditMinor)->toBe(30_001)
        ->and($price->discountMinor)->toBe(11_999)
        ->and($price->taxableMinor())->toBe(108_000)
        ->and($price->vatMinor)->toBe(16_200)
        ->and($price->totalMinor)->toBe(124_200);
});

it('caps a fixed coupon at the base', function () {
    $calculator = new PricingCalculator;

    expect($calculator->breakdown(20_000, 0, billingPricingCoupon(['discount_type' => DiscountType::Fixed, 'amount_minor' => 5_000]), 1500)->discountMinor)->toBe(5_000)
        ->and($calculator->breakdown(20_000, 0, billingPricingCoupon(['discount_type' => DiscountType::Fixed, 'amount_minor' => 50_000]), 1500)->totalMinor)->toBe(0);
});

it('uses the voucher balance, capped at the base', function () {
    $voucher = billingPricingCoupon(['kind' => CouponKind::Voucher, 'discount_type' => DiscountType::Fixed, 'amount_minor' => 40_000, 'balance_minor' => 15_000]);
    $price = (new PricingCalculator)->breakdown(40_000, 0, $voucher, 1500);

    expect($price->discountMinor)->toBe(15_000)
        ->and($price->vatMinor)->toBe(3_750)
        ->and($price->totalMinor)->toBe(28_750);

    $voucher->balance_minor = 90_000;

    expect((new PricingCalculator)->breakdown(40_000, 0, $voucher, 1500)->totalMinor)->toBe(0);
});

it('caps the upgrade credit at the subtotal', function () {
    $price = (new PricingCalculator)->breakdown(10_000, 25_000, null, 1500);

    expect($price->creditMinor)->toBe(10_000)
        ->and($price->totalMinor)->toBe(0);
});

it('computes the pro-rata upgrade credit with floor', function () {
    $start = CarbonImmutable::parse('2026-10-01T00:00:00Z');
    $current = new Subscription;
    $current->forceFill([
        'interval' => BillingInterval::Monthly,
        'starts_at' => $start,
        'ends_at' => $start->addDays(30),
        'subtotal_minor' => 90_000,
        'discount_minor' => 9_000,
        'credit_minor' => 1_000,
    ]);

    $calculator = new PricingCalculator;

    // net 80000 × (20 days / 30 days) = 53333.33 → 53333
    expect($calculator->upgradeCredit($current, $start->addDays(10)))->toBe(53_333)
        ->and($calculator->upgradeCredit($current, $start))->toBe(80_000)
        ->and($calculator->upgradeCredit($current, $start->addDays(31)))->toBe(0);
});
