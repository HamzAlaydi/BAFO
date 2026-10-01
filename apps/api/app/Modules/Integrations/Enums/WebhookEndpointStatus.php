<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

/**
 * `webhook_endpoints.status` (ARCHITECTURE §5.4, §6.6).
 */
enum WebhookEndpointStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';

    /** Label in the given locale (default: the app locale). Key: `integrations.enums.webhook_endpoint_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('integrations.enums.webhook_endpoint_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
