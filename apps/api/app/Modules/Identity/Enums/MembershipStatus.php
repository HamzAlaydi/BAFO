<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * `memberships.status` (ARCHITECTURE §5.3, §6.6).
 */
enum MembershipStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Inactive = 'inactive';

    /** Label in the given locale (default: the app locale). Key: `identity.enums.membership_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('identity.enums.membership_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
