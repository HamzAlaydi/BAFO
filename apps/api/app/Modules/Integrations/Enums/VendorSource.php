<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

/**
 * `vendors.source` (ARCHITECTURE §5.4).
 */
enum VendorSource: string
{
    case Web = 'web';
    case Api = 'api';
    case Import = 'import';

    /** Label in the given locale (default: the app locale). Key: `integrations.enums.vendor_source.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('integrations.enums.vendor_source.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
