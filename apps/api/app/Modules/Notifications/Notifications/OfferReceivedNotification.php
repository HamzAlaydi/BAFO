<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `offer.received` (ARCHITECTURE §11.3).
 */
final class OfferReceivedNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::OfferReceived;
    }
}
