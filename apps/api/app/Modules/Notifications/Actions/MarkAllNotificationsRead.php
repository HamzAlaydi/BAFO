<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Broadcasting\UnreadCountBroadcast;
use App\Modules\Notifications\Services\NotificationInbox;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * `POST /notifications/read-all` (API.md §1.8), then `notifications.unread_count`.
 */
final readonly class MarkAllNotificationsRead
{
    public function __construct(private NotificationInbox $inbox) {}

    /**
     * @return int the unread count afterwards (0)
     */
    public function handle(User $user): int
    {
        DB::transaction(fn (): int => $this->inbox->query($user)
            ->reorder()
            ->whereNull('read_at')
            ->update(['read_at' => Date::now()]));

        $unread = $this->inbox->unreadCount($user);

        event(new UnreadCountBroadcast($user->public_id, $unread));

        return $unread;
    }
}
