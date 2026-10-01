<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * `subscriptions.status` (ARCHITECTURE §5.7, §6.5).
 */
enum SubscriptionStatus: string
{
    case PendingPayment = 'pending_payment';
    case Active = 'active';
    case Superseded = 'superseded';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.subscription_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.subscription_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
