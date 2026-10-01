<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Broadcasting\UnreadCountBroadcast;
use App\Modules\Notifications\Services\NotificationInbox;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

/**
 * `DELETE /notifications/{notification}` (API.md §1.8), then `notifications.unread_count`.
 */
final readonly class DeleteNotification
{
    public function __construct(private NotificationInbox $inbox) {}

    public function handle(User $user, DatabaseNotification $notification): void
    {
        DB::transaction(static fn () => $notification->delete());

        event(new UnreadCountBroadcast($user->public_id, $this->inbox->unreadCount($user)));
    }
}
