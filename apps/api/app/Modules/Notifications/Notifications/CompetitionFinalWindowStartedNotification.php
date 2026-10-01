<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `competition.final_window_started` (ARCHITECTURE §11.3).
 */
final class CompetitionFinalWindowStartedNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::CompetitionFinalWindowStarted;
    }
}
