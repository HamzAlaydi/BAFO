<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `export.finished` (ARCHITECTURE §11.3).
 */
final class ExportFinishedNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::ExportFinished;
    }
}
