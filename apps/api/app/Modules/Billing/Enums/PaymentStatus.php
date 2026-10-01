<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * `payments.status` (ARCHITECTURE §5.7, §6.4).
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';
    case Refunded = 'refunded';

    /**
     * Terminal for HandleGatewayResult: nothing changes any more, except the late
     * `expired → succeeded` confirmation (ARCHITECTURE §6.4).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Refunded], true);
    }

    /** Label in the given locale (default: the app locale). Key: `billing.enums.payment_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.payment_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
