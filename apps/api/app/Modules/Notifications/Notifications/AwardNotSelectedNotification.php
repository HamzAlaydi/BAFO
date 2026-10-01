<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `award.not_selected` (ARCHITECTURE §11.3).
 */
final class AwardNotSelectedNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::AwardNotSelected;
    }
}
