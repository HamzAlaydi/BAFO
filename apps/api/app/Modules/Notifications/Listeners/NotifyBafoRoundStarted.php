<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Notifications\Services\BiddingNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `bafo.invited` on `App\Modules\Bidding\Events\BafoRoundStarted` (ARCHITECTURE §10, §11.3).
 */
final class NotifyBafoRoundStarted extends QueuedNotificationListener
{
    public function __construct(private readonly BiddingNotifier $notifier) {}

    /**
     * @param  object  $event  BafoRoundStarted
     */
    public function handle(object $event): void
    {
        $this->notifier->bafoRoundStarted(
            EventPayload::instance($event, 'round', BafoRound::class),
            EventPayload::instance($event, 'competition', Competition::class),
        );
    }
}
