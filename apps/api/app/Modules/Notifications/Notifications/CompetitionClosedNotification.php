<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `competition.closed` (ARCHITECTURE §11.3).
 */
final class CompetitionClosedNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::CompetitionClosed;
    }
}
