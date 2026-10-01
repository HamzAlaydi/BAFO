<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `sponsorship.unused_passes` (ARCHITECTURE §11.3).
 */
final class SponsorshipUnusedPassesNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::SponsorshipUnusedPasses;
    }
}
