<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Enums;

/**
 * `invitations.revoke_reason` (ARCHITECTURE §5.5, §6.2).
 */
enum RevokeReason: string
{
    case Issuer = 'issuer';
    case DuplicateOrganization = 'duplicate_organization';
    case Admin = 'admin';

    /** Label in the given locale (default: the app locale). Key: `competitions.enums.revoke_reason.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('competitions.enums.revoke_reason.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
