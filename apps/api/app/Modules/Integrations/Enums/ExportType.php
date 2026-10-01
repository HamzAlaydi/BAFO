<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

/**
 * `export_jobs.type` (ARCHITECTURE §5.4, §14.8).
 */
enum ExportType: string
{
    case Results = 'results';
    case OfferLog = 'offer_log';
    case Awards = 'awards';
    case Vendors = 'vendors';

    /** Label in the given locale (default: the app locale). Key: `integrations.enums.export_type.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('integrations.enums.export_type.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
