<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * `coupons.kind` (ARCHITECTURE §5.7, §13.4).
 */
enum CouponKind: string
{
    case Coupon = 'coupon';
    case Voucher = 'voucher';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.coupon_kind.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.coupon_kind.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
