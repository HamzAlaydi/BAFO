<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Notifications\Services\CompetitionNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `competition.updated` on `App\Modules\Competitions\Events\CompetitionUpdated` (ARCHITECTURE §10, §11.3).
 */
final class NotifyCompetitionUpdated extends QueuedNotificationListener
{
    public function __construct(private readonly CompetitionNotifier $notifier) {}

    /**
     * @param  object  $event  CompetitionUpdated
     */
    public function handle(object $event): void
    {
        $this->notifier->updated(
            EventPayload::instance($event, 'competition', Competition::class),
            EventPayload::strings($event, 'fields'),
        );
    }
}
