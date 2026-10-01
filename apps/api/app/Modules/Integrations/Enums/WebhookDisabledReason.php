<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

/**
 * `webhook_endpoints.disabled_reason` (ARCHITECTURE §5.4, str(20)).
 *
 * CONTRACT-GAP: the contract lists the values (`manual`, `failing`) but names no enum; every enumerated
 * column is an enum (CONVENTIONS §2.1), so this one is named after the column.
 */
enum WebhookDisabledReason: string
{
    case Manual = 'manual';
    case Failing = 'failing';

    /** Label in the given locale (default: the app locale). Key: `integrations.enums.webhook_disabled_reason.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('integrations.enums.webhook_disabled_reason.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
