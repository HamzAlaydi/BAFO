<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Enums;

/**
 * `invitations.status` (ARCHITECTURE §5.5, §6.2).
 */
enum InvitationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Joined = 'joined';
    case Declined = 'declined';
    case Revoked = 'revoked';
    case Expired = 'expired';

    /** Label in the given locale (default: the app locale). Key: `competitions.enums.invitation_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('competitions.enums.invitation_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
