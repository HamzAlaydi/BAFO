<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * `live.updated` on `private-competition.{id}.participant.{orgId}` with that participant's own
 * `ParticipantLiveSnapshot` (ARCHITECTURE §9.3, D9: every participant event is projected per
 * organization).
 */
final class ParticipantLiveUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public string $queue = 'live';

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public readonly string $competitionId,
        public readonly string $organizationId,
        public readonly array $snapshot,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('competition.'.$this->competitionId.'.participant.'.$this->organizationId)];
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
