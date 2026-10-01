<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Broadcasting\NotificationCreatedBroadcast;
use App\Modules\Notifications\Enums\DeliveryChannel;
use App\Modules\Notifications\Http\Resources\NotificationResource;
use App\Modules\Notifications\Notifications\BafoNotification;
use App\Modules\Notifications\Services\NotificationInbox;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Follows every stored in-app notification with `notification.created` on the recipient's
 * `user.{id}` channel (ARCHITECTURE §11.1: the broadcast is dispatched from a `NotificationSent`
 * listener for the database channel, not as a per-notification channel).
 *
 * Synchronous: it runs inside the queued `database` job, in the recipient's language, right
 * after the row is written.
 */
final readonly class BroadcastNewNotification
{
    public function __construct(private NotificationInbox $inbox) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== DeliveryChannel::Database->value
            || ! $event->notification instanceof BafoNotification
            || ! $event->notifiable instanceof User
            || ! $event->response instanceof DatabaseNotification) {
            return;
        }

        event(new NotificationCreatedBroadcast($event->notifiable->public_id, [
            'notification' => NotificationResource::make($event->response)->resolve(),
            'unread_count' => $this->inbox->unreadCount($event->notifiable),
        ]));
    }
}
