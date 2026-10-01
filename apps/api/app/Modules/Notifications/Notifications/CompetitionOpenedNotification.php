<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `competition.opened` (ARCHITECTURE §11.3).
 */
final class CompetitionOpenedNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::CompetitionOpened;
    }
}
