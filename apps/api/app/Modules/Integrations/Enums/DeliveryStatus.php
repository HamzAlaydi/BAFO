<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

/**
 * `webhook_deliveries.status` (ARCHITECTURE §5.4, §6.6).
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /** Label in the given locale (default: the app locale). Key: `integrations.enums.delivery_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('integrations.enums.delivery_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
