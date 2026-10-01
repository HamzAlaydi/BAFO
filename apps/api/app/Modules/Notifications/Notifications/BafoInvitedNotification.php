<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `bafo.invited` (ARCHITECTURE §11.3).
 */
final class BafoInvitedNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::BafoInvited;
    }
}
