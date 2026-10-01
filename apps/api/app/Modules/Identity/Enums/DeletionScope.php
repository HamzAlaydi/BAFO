<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * `account_deletion_requests.scope` (ARCHITECTURE §5.3, §13.8).
 */
enum DeletionScope: string
{
    case User = 'user';
    case Organization = 'organization';

    /** Label in the given locale (default: the app locale). Key: `identity.enums.deletion_scope.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('identity.enums.deletion_scope.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
