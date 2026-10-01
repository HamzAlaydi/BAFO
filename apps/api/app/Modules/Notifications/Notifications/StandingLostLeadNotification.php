<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `standing.lost_lead` (ARCHITECTURE §11.3).
 */
final class StandingLostLeadNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::StandingLostLead;
    }
}
