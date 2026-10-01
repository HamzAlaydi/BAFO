<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * `organizations.status` (ARCHITECTURE §5.3, §6.6).
 */
enum OrganizationStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Deleted = 'deleted';

    /** Label in the given locale (default: the app locale). Key: `identity.enums.organization_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('identity.enums.organization_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
