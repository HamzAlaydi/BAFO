<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `offer.voided` (ARCHITECTURE §11.3).
 */
final class OfferVoidedNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::OfferVoided;
    }
}
