<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Enums;

/**
 * `awards.erp_sync_status` (ARCHITECTURE §5.6).
 */
enum ErpSyncStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';

    /** Label in the given locale (default: the app locale). Key: `bidding.enums.erp_sync_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('bidding.enums.erp_sync_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
