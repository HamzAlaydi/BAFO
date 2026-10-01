<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Listeners;

use App\Modules\Bidding\Events\OfferAccepted;
use App\Modules\Bidding\Services\LiveBroadcaster;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * `live.updated` (issuer + the §7.10 participant recipients) and `offer.accepted` after an offer
 * commits. The snapshots are built when the listener runs, so they may already include later
 * offers: their `v` says so.
 */
final class BroadcastOfferAccepted implements ShouldHandleEventsAfterCommit, ShouldQueueAfterCommit
{
    public string $queue = 'live';

    public function __construct(private readonly LiveBroadcaster $broadcaster) {}

    public function handle(OfferAccepted $event): void
    {
        $this->broadcaster->offerAccepted($event->offer, $event->competition, $event->context);
    }
}
