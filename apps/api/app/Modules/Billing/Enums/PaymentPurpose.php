<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * `payments.purpose` (ARCHITECTURE §5.7, §13.1).
 */
enum PaymentPurpose: string
{
    case Subscription = 'subscription';
    case Sponsorship = 'sponsorship';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.payment_purpose.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.payment_purpose.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
