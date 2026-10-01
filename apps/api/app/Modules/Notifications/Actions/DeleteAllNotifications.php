<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Broadcasting\UnreadCountBroadcast;
use App\Modules\Notifications\Services\NotificationInbox;
use Illuminate\Support\Facades\DB;

/**
 * `DELETE /notifications` (API.md §1.8): clears the user's inbox, then `notifications.unread_count`.
 */
final readonly class DeleteAllNotifications
{
    public function __construct(private NotificationInbox $inbox) {}

    public function handle(User $user): void
    {
        DB::transaction(fn (): mixed => $this->inbox->query($user)->reorder()->delete());

        event(new UnreadCountBroadcast($user->public_id, 0));
    }
}
