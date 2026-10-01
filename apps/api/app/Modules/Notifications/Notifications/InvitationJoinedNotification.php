<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `invitation.joined` (ARCHITECTURE §11.3).
 */
final class InvitationJoinedNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::InvitationJoined;
    }
}
