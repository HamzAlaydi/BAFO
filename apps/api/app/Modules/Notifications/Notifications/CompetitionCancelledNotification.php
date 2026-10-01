<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `competition.cancelled` (ARCHITECTURE §11.3).
 */
final class CompetitionCancelledNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::CompetitionCancelled;
    }
}
