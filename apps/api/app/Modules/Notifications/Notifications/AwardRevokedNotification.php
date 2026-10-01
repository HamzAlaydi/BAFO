<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `award.revoked` (ARCHITECTURE §11.3).
 */
final class AwardRevokedNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::AwardRevoked;
    }
}
