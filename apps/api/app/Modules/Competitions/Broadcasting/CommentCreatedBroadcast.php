<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * `comment.created` (ARCHITECTURE §9.3): the Comment resource projected for one audience, so one
 * broadcast per channel (participants see aliases, never other participants' names).
 */
final class CommentCreatedBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public string $queue = 'live';

    /**
     * @param  array<string, mixed>  $comment
     */
    public function __construct(
        public readonly string $channel,
        public readonly array $comment,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel($this->channel);
    }

    public function broadcastAs(): string
    {
        return 'comment.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->comment;
    }
}
