<?php

declare(strict_types=1);

namespace App\Modules\Platform\Enums;

/**
 * Handling state of a contact message in the admin inbox (contact_messages.status).
 */
enum ContactStatus: string
{
    case New = 'new';
    case Read = 'read';
    case Archived = 'archived';

    public function label(?string $locale = null): string
    {
        $label = __('platform.enums.contact_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
