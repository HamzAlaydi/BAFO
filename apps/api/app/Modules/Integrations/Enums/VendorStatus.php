<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

/**
 * `vendors.status` (ARCHITECTURE §5.4). Blocked vendors cannot be invited.
 */
enum VendorStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
    case Archived = 'archived';

    /** Label in the given locale (default: the app locale). Key: `integrations.enums.vendor_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('integrations.enums.vendor_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
