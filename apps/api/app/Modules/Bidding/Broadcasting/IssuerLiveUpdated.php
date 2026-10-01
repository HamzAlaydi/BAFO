<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * `live.updated` on `private-competition.{id}` with an `IssuerLiveSnapshot` (ARCHITECTURE §9.3).
 * The payload is projected before dispatch (VisibilityProjector).
 */
final class IssuerLiveUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public string $queue = 'live';

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public readonly string $competitionId,
        public readonly array $snapshot,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('competition.'.$this->competitionId)];
    }

    public function broadcastAs(): string
    {
        return 'live.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->snapshot;
    }
}
