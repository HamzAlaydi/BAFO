<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * `account_deletion_requests.status` (ARCHITECTURE §5.3, §6.6).
 */
enum DeletionStatus: string
{
    case Pending = 'pending';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    /** Label in the given locale (default: the app locale). Key: `identity.enums.deletion_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('identity.enums.deletion_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
