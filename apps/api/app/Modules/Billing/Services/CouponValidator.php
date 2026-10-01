<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Enums\CouponScope;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\CouponRedemption;
use App\Modules\Billing\Models\Payment;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;

/**
 * Coupon and voucher validation (ARCHITECTURE §13.4), for `POST /billing/coupons/validate`
 * and at checkout. The checks run in the contract order; every failure is a 422.
 */
final class CouponValidator
{
    /**
     * @throws ApiException coupon_invalid, coupon_expired, coupon_not_applicable or coupon_exhausted
     */
    public function validate(string $code, int $organizationId, PaymentPurpose $purpose, ?CarbonImmutable $at = null): Coupon
    {
        $at ??= CarbonImmutable::now();
        $coupon = Coupon::query()->where('code', mb_strtoupper(trim($code)))->first();

        // 1. The code exists and is active. 3. Org-scoped coupons belong to the caller. The
        // ownership check runs before the validity one, so that another organization's voucher
        // never answers `coupon_expired` (never reveal other organizations' codes, SECURITY_REVIEW S-04).
        if ($coupon === null || ! $coupon->is_active
            || ($coupon->organization_id !== null && $coupon->organization_id !== $organizationId)) {
            throw $this->error('coupon_invalid');
        }

        // 2. Within its validity.
        if (($coupon->valid_from !== null && $at->lessThan($coupon->valid_from))
            || ($coupon->valid_until !== null && $at->greaterThanOrEqualTo($coupon->valid_until))) {
            throw $this->error('coupon_expired');
        }

        // 4. The purpose matches.
        if ($coupon->applies_to !== CouponScope::Any && $coupon->applies_to->value !== $purpose->value) {
            throw $this->error('coupon_not_applicable');
        }

        // 5. Redemptions, the per-organization limit, the voucher balance and pending use.
        if ($this->isExhausted($coupon, $organizationId, $at)) {
            throw $this->error('coupon_exhausted');
        }

        return $coupon;
    }

    /**
     * SECURITY_REVIEW S-04: the organization's own open checkouts that carry the coupon count as
     * uses, so a limit cannot be multiplied by opening several checkouts before paying any. Other
     * organizations' open checkouts do not count (they could otherwise lock a public coupon).
     */
    private function isExhausted(Coupon $coupon, int $organizationId, CarbonImmutable $at): bool
    {
        $held = $this->heldByOpenCheckouts($coupon, $organizationId, $at);

        if ($coupon->max_redemptions !== null && $coupon->redemptions_count + $held >= $coupon->max_redemptions) {
            return true;
        }

        if ($coupon->per_organization_limit !== null) {
            $used = CouponRedemption::query()
                ->where('coupon_id', $coupon->id)
                ->where('organization_id', $organizationId)
                ->count();

            if ($used + $held >= $coupon->per_organization_limit) {
                return true;
            }
        }

        if (! $coupon->isVoucher()) {
            return false;
        }

        if ((int) ($coupon->balance_minor ?? 0) <= 0) {
            return true;
        }

        return $held > 0;
    }

    /**
     * The organization's pending, unexpired payments that use the coupon.
     */
    private function heldByOpenCheckouts(Coupon $coupon, int $organizationId, CarbonImmutable $at): int
    {
        return Payment::query()
            ->where('organization_id', $organizationId)
            ->where('coupon_id', $coupon->id)
            ->where('status', PaymentStatus::Pending->value)
            ->where('expires_at', '>', $at)
            ->count();
    }

    private function error(string $code): ApiException
    {
        return new ApiException($code, 'billing.errors.'.$code, 422);
    }
}
