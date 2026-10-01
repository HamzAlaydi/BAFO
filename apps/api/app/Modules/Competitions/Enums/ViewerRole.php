<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Enums;

/**
 * Who is looking at a competition (ARCHITECTURE §3.6 `Viewer`, §8.2). Rendered as
 * `viewer_role` in the Competition resource (API.md §2.6).
 *
 * CONTRACT-GAP: §3.6 lists the role values but no type; a string-backed enum (CONVENTIONS §2.1).
 */
enum ViewerRole: string
{
    case Issuer = 'issuer';
    case Participant = 'participant';
    case Invitee = 'invitee';
    case ApiClient = 'api_client';

    /** Label in the given locale (default: the app locale). Key: `competitions.enums.viewer_role.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('competitions.enums.viewer_role.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
