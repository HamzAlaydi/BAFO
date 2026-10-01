<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Broadcasting\UnreadCountBroadcast;
use App\Modules\Notifications\Services\NotificationInbox;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

/**
 * `POST /notifications/{notification}/read` (API.md §1.8). Idempotent: an already read
 * notification keeps its `read_at`. A change is followed by `notifications.unread_count`.
 *
 * CONTRACT-GAP: inbox changes (read, delete) are personal state, not business state; they are
 * not written to the audit log (§4.5), which would otherwise grow with every click.
 */
final readonly class MarkNotificationRead
{
    public function __construct(private NotificationInbox $inbox) {}

    public function handle(User $user, DatabaseNotification $notification): DatabaseNotification
    {
        if ($notification->getAttribute('read_at') !== null) {
            return $notification;
        }

        DB::transaction(static fn () => $notification->markAsRead());

        event(new UnreadCountBroadcast($user->public_id, $this->inbox->unreadCount($user)));

        return $notification;
    }
}
