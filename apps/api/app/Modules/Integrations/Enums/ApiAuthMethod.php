<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

/**
 * How a public API request authenticated (ARCHITECTURE §14.2; `auth` of `GET /client`, API.md §3.1).
 */
enum ApiAuthMethod: string
{
    case OAuth = 'oauth';
    case ApiKey = 'api_key';

    /** Label in the given locale (default: the app locale). Key: `integrations.enums.api_auth_method.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('integrations.enums.api_auth_method.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
