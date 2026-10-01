<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

/**
 * `export_jobs.format` (ARCHITECTURE §5.4, §14.8).
 */
enum ExportFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';

    /** Label in the given locale (default: the app locale). Key: `integrations.enums.export_format.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('integrations.enums.export_format.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
