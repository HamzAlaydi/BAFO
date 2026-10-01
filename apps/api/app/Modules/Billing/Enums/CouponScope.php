<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * `coupons.applies_to` (ARCHITECTURE §5.7).
 */
enum CouponScope: string
{
    case Any = 'any';
    case Subscription = 'subscription';
    case Sponsorship = 'sponsorship';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.coupon_scope.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.coupon_scope.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
