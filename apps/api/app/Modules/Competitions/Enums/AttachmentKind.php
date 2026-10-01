<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Enums;

/**
 * `competition_attachments.kind` (ARCHITECTURE §5.5, §8.5).
 */
enum AttachmentKind: string
{
    case Document = 'document';
    case InvitationDocument = 'invitation_document';
    case ExternalLink = 'external_link';

    /** Label in the given locale (default: the app locale). Key: `competitions.enums.attachment_kind.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('competitions.enums.attachment_kind.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
