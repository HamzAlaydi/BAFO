<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Bidding\Models\Award;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Notifications\Services\BiddingNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `award.revoked` on `App\Modules\Bidding\Events\AwardRevoked` (ARCHITECTURE §10, §11.3).
 */
final class NotifyAwardRevoked extends QueuedNotificationListener
{
    public function __construct(private readonly BiddingNotifier $notifier) {}

    /**
     * @param  object  $event  AwardRevoked
     */
    public function handle(object $event): void
    {
        $this->notifier->awardRevoked(
            EventPayload::instance($event, 'award', Award::class),
            EventPayload::instance($event, 'competition', Competition::class),
        );
    }
}
