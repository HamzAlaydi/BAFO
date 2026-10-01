<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * `subscriptions.source` (ARCHITECTURE §5.7, §13.3).
 */
enum SubscriptionSource: string
{
    case Paid = 'paid';
    case Trial = 'trial';
    case Grant = 'grant';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.subscription_source.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.subscription_source.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
