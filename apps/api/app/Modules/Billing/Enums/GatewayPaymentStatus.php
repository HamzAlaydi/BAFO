<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * A payment's state as the gateway reports it (ARCHITECTURE §13.6 `GatewayPaymentState`).
 */
enum GatewayPaymentStatus: string
{
    case Paid = 'paid';
    case Failed = 'failed';
    case Pending = 'pending';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.gateway_payment_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.gateway_payment_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
