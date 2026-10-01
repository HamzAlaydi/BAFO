<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Contracts;

use App\Modules\Notifications\Data\PushMessage;
use App\Modules\Notifications\Models\DeviceToken;
use Illuminate\Support\Collection;

/**
 * Sends a push message to devices (ARCHITECTURE §15.1). Drivers are selected by
 * `bafo.notifications.push.driver` (`PUSH_DRIVER`); only `log` exists until a Firebase project does.
 */
interface PushNotifier
{
    /**
     * @param  Collection<int, DeviceToken>  $tokens
     */
    public function send(Collection $tokens, PushMessage $message): void;
}
