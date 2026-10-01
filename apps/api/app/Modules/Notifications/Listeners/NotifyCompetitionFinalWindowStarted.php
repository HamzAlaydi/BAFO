<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Notifications\Services\CompetitionNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `competition.final_window_started` on `App\Modules\Competitions\Events\CompetitionFinalWindowStarted` (ARCHITECTURE §10, §11.3).
 */
final class NotifyCompetitionFinalWindowStarted extends QueuedNotificationListener
{
    public function __construct(private readonly CompetitionNotifier $notifier) {}

    /**
     * @param  object  $event  CompetitionFinalWindowStarted
     */
    public function handle(object $event): void
    {
        $this->notifier->finalWindowStarted(EventPayload::instance($event, 'competition', Competition::class));
    }
}
