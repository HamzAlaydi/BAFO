<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

/**
 * `import_jobs.type` (ARCHITECTURE §5.4, §14.7).
 */
enum ImportType: string
{
    case Vendors = 'vendors';

    /** Label in the given locale (default: the app locale). Key: `integrations.enums.import_type.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('integrations.enums.import_type.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
