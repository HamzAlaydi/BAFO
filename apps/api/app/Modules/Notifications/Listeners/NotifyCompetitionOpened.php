<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Notifications\Services\CompetitionNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `competition.opened` on `App\Modules\Competitions\Events\CompetitionOpened` (ARCHITECTURE §10, §11.3).
 */
final class NotifyCompetitionOpened extends QueuedNotificationListener
{
    public function __construct(private readonly CompetitionNotifier $notifier) {}

    /**
     * @param  object  $event  CompetitionOpened
     */
    public function handle(object $event): void
    {
        $this->notifier->opened(EventPayload::instance($event, 'competition', Competition::class));
    }
}
