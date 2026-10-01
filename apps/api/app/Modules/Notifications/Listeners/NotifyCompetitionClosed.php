<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Notifications\Services\CompetitionNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `competition.closed` on `App\Modules\Competitions\Events\CompetitionClosed` (ARCHITECTURE §10, §11.3).
 */
final class NotifyCompetitionClosed extends QueuedNotificationListener
{
    public function __construct(private readonly CompetitionNotifier $notifier) {}

    /**
     * @param  object  $event  CompetitionClosed
     */
    public function handle(object $event): void
    {
        $this->notifier->closed(EventPayload::instance($event, 'competition', Competition::class));
    }
}
