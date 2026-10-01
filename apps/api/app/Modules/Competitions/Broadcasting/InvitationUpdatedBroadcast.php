<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * `invitation.updated` on the issuer channel (ARCHITECTURE §9.3): the Invitation resource,
 * issuer view (API.md §2.7).
 */
final class InvitationUpdatedBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public string $queue = 'live';

    /**
     * @param  array<string, mixed>  $invitation
     */
    public function __construct(
        public readonly string $competitionId,
        public readonly array $invitation,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('competition.'.$this->competitionId);
    }

    public function broadcastAs(): string
    {
        return 'invitation.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->invitation;
    }
}
