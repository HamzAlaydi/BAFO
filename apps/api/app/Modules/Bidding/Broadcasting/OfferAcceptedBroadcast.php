<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * `offer.accepted` on the issuer channel with an `OfferLogEntry` (issuer projection: the amount
 * is null while sealed). Clients append by `seq` and fetch `…/offers/log?after_seq=` on a gap.
 */
final class OfferAcceptedBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public string $queue = 'live';

    /**
     * @param  array<string, mixed>  $entry
     */
    public function __construct(
        public readonly string $competitionId,
        public readonly array $entry,
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
        return 'offer.accepted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->entry;
    }
}
