<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/**
 * The delivery channels of a catalogue notification (ARCHITECTURE §11.1). The values are the
 * Laravel channel names: `database` (in-app), `mail` and the custom `push` channel.
 * Realtime (`broadcast`) is not a per-notification channel: `NotificationCreatedBroadcast`
 * follows every in-app row.
 */
enum DeliveryChannel: string
{
    case Database = 'database';
    case Push = 'push';
    case Mail = 'mail';

    /** Label in the given locale (default: the app locale). Key: `notifications.enums.delivery_channel.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('notifications.enums.delivery_channel.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
