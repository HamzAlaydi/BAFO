<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

/**
 * `api_clients.status` (ARCHITECTURE §5.4, §14.1).
 */
enum ApiClientStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';

    /** Label in the given locale (default: the app locale). Key: `integrations.enums.api_client_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('integrations.enums.api_client_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
