<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `invitation.declined` (ARCHITECTURE §11.3).
 */
final class InvitationDeclinedNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::InvitationDeclined;
    }
}
