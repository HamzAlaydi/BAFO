<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `subscription.expired` (ARCHITECTURE §11.3).
 */
final class SubscriptionExpiredNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::SubscriptionExpired;
    }
}
