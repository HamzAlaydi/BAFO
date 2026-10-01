<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * `coupons.discount_type` (ARCHITECTURE §5.7).
 */
enum DiscountType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.discount_type.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.discount_type.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
