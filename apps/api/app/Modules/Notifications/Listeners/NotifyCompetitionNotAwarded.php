<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Notifications\Services\CompetitionNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `competition.not_awarded` on `App\Modules\Competitions\Events\CompetitionClosedWithoutAward` (ARCHITECTURE §10, §11.3).
 */
final class NotifyCompetitionNotAwarded extends QueuedNotificationListener
{
    public function __construct(private readonly CompetitionNotifier $notifier) {}

    /**
     * @param  object  $event  CompetitionClosedWithoutAward
     */
    public function handle(object $event): void
    {
        $this->notifier->notAwarded(EventPayload::instance($event, 'competition', Competition::class));
    }
}
