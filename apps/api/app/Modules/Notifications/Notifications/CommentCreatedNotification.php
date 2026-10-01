<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `comment.created` (ARCHITECTURE §11.3).
 */
final class CommentCreatedNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::CommentCreated;
    }
}
