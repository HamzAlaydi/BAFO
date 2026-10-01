<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Data\PriceBreakdown;
use App\Modules\Billing\Enums\CouponKind;
use App\Modules\Billing\Enums\DiscountType;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Subscription;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;

/**
 * The checkout math of ARCHITECTURE §13.2, in integer halalas only (no floats):
 *
 *     credit   = upgrade only: floor(currentNet × remainingSeconds / totalSeconds), capped at subtotal
 *     discount = percent: floor((subtotal − credit) × percent_bps / 10000)
 *                fixed:   min(amount_minor, subtotal − credit)
 *                voucher: min(balance_minor, subtotal − credit)
 *     taxable  = subtotal − credit − discount
 *     vat      = Money::vat(taxable, rate)      (half up)
 *     total    = taxable + vat
 */
final readonly class PricingCalculator
{
    public function breakdown(int $subtotalMinor, int $creditMinor, ?Coupon $coupon, int $vatRateBp): PriceBreakdown
    {
        $subtotal = max(0, $subtotalMinor);
        $credit = min(max(0, $creditMinor), $subtotal);
        $discount = $coupon === null ? 0 : $this->discount($coupon, $subtotal - $credit);
        $taxable = $subtotal - $credit - $discount;
        $vat = Money::vat($taxable, $vatRateBp);

        return new PriceBreakdown($subtotal, $credit, $discount, $vatRateBp, $vat, $taxable + $vat);
    }

    /**
     * The discount a coupon or voucher gives on `$baseMinor` (subtotal − credit), never more
     * than the base.
     */
    public function discount(Coupon $coupon, int $baseMinor): int
    {
        $base = max(0, $baseMinor);

        $discount = match (true) {
            $coupon->kind === CouponKind::Voucher => (int) ($coupon->balance_minor ?? 0),
            $coupon->discount_type === DiscountType::Percent => intdiv($base * (int) ($coupon->percent_bps ?? 0), 10_000),
            default => (int) ($coupon->amount_minor ?? 0),
        };

        return min(max(0, $discount), $base);
    }

    /**
     * The pro-rata value left on the current subscription at `$at` (ARCHITECTURE §13.2), before
     * the cap at the new subtotal.
     */
    public function upgradeCredit(Subscription $current, CarbonImmutable $at): int
    {
        if ($current->starts_at === null || $current->ends_at === null) {
            return 0;
        }

        $totalSeconds = (int) $current->starts_at->diffInSeconds($current->ends_at, true);
        $remainingSeconds = max(0, (int) $at->diffInSeconds($current->ends_at, false));

        if ($totalSeconds <= 0 || $remainingSeconds <= 0) {
            return 0;
        }

        $net = (int) ($current->subtotal_minor ?? 0) - (int) ($current->discount_minor ?? 0) - (int) ($current->credit_minor ?? 0);

        if ($net <= 0) {
            return 0;
        }

        return intdiv($net * min($remainingSeconds, $totalSeconds), $totalSeconds);
    }
}
