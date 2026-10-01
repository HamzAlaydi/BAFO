<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Notifications\Services\CompetitionNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `competition.closing_soon` on `App\Modules\Competitions\Events\CompetitionClosingSoon` (ARCHITECTURE §10, §11.3).
 */
final class NotifyCompetitionClosingSoon extends QueuedNotificationListener
{
    public function __construct(private readonly CompetitionNotifier $notifier) {}

    /**
     * @param  object  $event  CompetitionClosingSoon
     */
    public function handle(object $event): void
    {
        $this->notifier->closingSoon(
            EventPayload::instance($event, 'competition', Competition::class),
            EventPayload::int($event, 'minutes'),
        );
    }
}
