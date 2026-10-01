<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * `notification.created` on `private-user.{userId}` (ARCHITECTURE §9.3, API.md §5):
 * `{notification: Notification, unread_count}`, sent for every new in-app notification. The
 * payload is rendered when the row is stored, in the recipient's language.
 */
final class NotificationCreatedBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public string $queue = 'live';

    /**
     * @param  array{notification: array<string, mixed>, unread_count: int}  $payload
     */
    public function __construct(
        public readonly string $userPublicId,
        public readonly array $payload,
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
        return 'notification.created';
    }

    /**
     * @return array{notification: array<string, mixed>, unread_count: int}
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
