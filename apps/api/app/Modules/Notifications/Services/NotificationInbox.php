<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A user's in-app notifications (the `notifications` rows of the user, ARCHITECTURE §5.8).
 * Identity may read `unreadCount()` for `Me.unread_notifications_count` (API.md §2.3).
 */
final class NotificationInbox
{
    /**
     * Newest first; ULIDs order rows created within the same second.
     *
     * @return Builder<DatabaseNotification>
     */
    public function query(User $user): Builder
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function unreadCount(User $user): int
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->whereNull('read_at')
            ->count();
    }

    public function owns(User $user, DatabaseNotification $notification): bool
    {
        return $notification->getAttribute('notifiable_type') === $user->getMorphClass()
            && (int) $notification->getAttribute('notifiable_id') === (int) $user->getKey();
    }
}
