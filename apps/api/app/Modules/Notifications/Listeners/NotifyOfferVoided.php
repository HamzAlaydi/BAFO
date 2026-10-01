<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Bidding\Models\Offer;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Notifications\Services\BiddingNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `offer.voided` on `App\Modules\Bidding\Events\OfferVoided` (ARCHITECTURE §10, §11.3).
 */
final class NotifyOfferVoided extends QueuedNotificationListener
{
    public function __construct(private readonly BiddingNotifier $notifier) {}

    /**
     * @param  object  $event  OfferVoided
     */
    public function handle(object $event): void
    {
        $this->notifier->offerVoided(
            EventPayload::instance($event, 'offer', Offer::class),
            EventPayload::instance($event, 'competition', Competition::class),
        );
    }
}
