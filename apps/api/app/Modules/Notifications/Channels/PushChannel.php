<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Contracts\PushNotifier;
use App\Modules\Notifications\Notifications\BafoNotification;

/**
 * The `push` notification channel (ARCHITECTURE §11.1): renders `toPush()` in the recipient's
 * language and hands it to the `PushNotifier` driver with the user's device tokens. A user
 * without a registered device gets nothing.
 */
final readonly class PushChannel
{
    public function __construct(private PushNotifier $notifier) {}

    public function send(object $notifiable, BafoNotification $notification): void
    {
        if (! $notifiable instanceof User) {
            return;
        }

        $tokens = $notifiable->deviceTokens()->get();

        if ($tokens->isEmpty()) {
            return;
        }

        $this->notifier->send($tokens, $notification->toPush($notifiable));
    }
}
