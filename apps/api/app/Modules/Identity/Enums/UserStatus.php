<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * `users.status` (ARCHITECTURE §5.3).
 */
enum UserStatus: string
{
    case Active = 'active';
    case PendingVerification = 'pending_verification';
    case Deleted = 'deleted';

    /** Label in the given locale (default: the app locale). Key: `identity.enums.user_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('identity.enums.user_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
