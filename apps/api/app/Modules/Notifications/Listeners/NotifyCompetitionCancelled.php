<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Notifications\Services\CompetitionNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `competition.cancelled` on `App\Modules\Competitions\Events\CompetitionCancelled` (ARCHITECTURE §10, §11.3).
 */
final class NotifyCompetitionCancelled extends QueuedNotificationListener
{
    public function __construct(private readonly CompetitionNotifier $notifier) {}

    /**
     * @param  object  $event  CompetitionCancelled
     */
    public function handle(object $event): void
    {
        $this->notifier->cancelled(EventPayload::instance($event, 'competition', Competition::class));
    }
}
