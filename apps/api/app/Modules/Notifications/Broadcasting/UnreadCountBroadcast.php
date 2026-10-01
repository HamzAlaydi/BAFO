<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * `notifications.unread_count` on `private-user.{userId}` (ARCHITECTURE §9.3, API.md §5):
 * `{unread_count}`, after a read, read-all or delete, so every open client syncs its badge.
 */
final class UnreadCountBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public string $queue = 'live';

    public function __construct(
        public readonly string $userPublicId,
        public readonly int $unreadCount,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.strtolower($this->userPublicId))];
    }

    public function broadcastAs(): string
    {
        return 'notifications.unread_count';
    }

    /**
     * @return array{unread_count: int}
     */
    public function broadcastWith(): array
    {
        return ['unread_count' => $this->unreadCount];
    }
}
