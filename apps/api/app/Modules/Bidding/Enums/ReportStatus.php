<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Enums;

/**
 * `competition_reports.status` (ARCHITECTURE §5.6, §7.14).
 */
enum ReportStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';

    /** Label in the given locale (default: the app locale). Key: `bidding.enums.report_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('bidding.enums.report_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
