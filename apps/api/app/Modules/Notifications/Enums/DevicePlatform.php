<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/**
 * `device_tokens.platform` (ARCHITECTURE §5.8).
 */
enum DevicePlatform: string
{
    case Ios = 'ios';
    case Android = 'android';
    case Web = 'web';

    /** Label in the given locale (default: the app locale). Key: `notifications.enums.device_platform.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('notifications.enums.device_platform.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
