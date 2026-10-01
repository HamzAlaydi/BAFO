<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * `payment_lines.kind` (ARCHITECTURE §5.7).
 */
enum PaymentLineKind: string
{
    case Plan = 'plan';
    case CustomSeats = 'custom_seats';
    case SponsoredPass = 'sponsored_pass';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.payment_line_kind.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.payment_line_kind.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
