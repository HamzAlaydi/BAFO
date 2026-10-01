<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Bidding\Models\Offer;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Notifications\Services\BiddingNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `offer.received` (digest) and `standing.lost_lead` on `App\Modules\Bidding\Events\OfferAccepted` (ARCHITECTURE §10, §11.3).
 */
final class NotifyOfferAccepted extends QueuedNotificationListener
{
    public function __construct(private readonly BiddingNotifier $notifier) {}

    /**
     * @param  object  $event  OfferAccepted
     */
    public function handle(object $event): void
    {
        $context = EventPayload::object($event, 'context'); // Bidding\Data\OfferAcceptedContext

        $this->notifier->offerAccepted(
            EventPayload::instance($event, 'offer', Offer::class),
            EventPayload::instance($event, 'competition', Competition::class),
            EventPayload::bool($context, 'leaderChanged'),
            EventPayload::nullableInt($context, 'previousLeaderParticipantId'),
        );
    }
}
