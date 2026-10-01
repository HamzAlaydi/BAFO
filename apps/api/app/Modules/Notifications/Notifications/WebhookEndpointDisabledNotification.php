<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `webhook.endpoint_disabled` (ARCHITECTURE §11.3).
 */
final class WebhookEndpointDisabledNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::WebhookEndpointDisabled;
    }
}
