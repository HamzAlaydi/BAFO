<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `competition.extended` (ARCHITECTURE §11.3).
 */
final class CompetitionExtendedNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::CompetitionExtended;
    }
}
