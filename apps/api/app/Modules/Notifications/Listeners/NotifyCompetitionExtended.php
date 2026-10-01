<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionExtension;
use App\Modules\Notifications\Services\CompetitionNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `competition.extended` on `App\Modules\Competitions\Events\CompetitionExtended` (ARCHITECTURE §10, §11.3).
 */
final class NotifyCompetitionExtended extends QueuedNotificationListener
{
    public function __construct(private readonly CompetitionNotifier $notifier) {}

    /**
     * @param  object  $event  CompetitionExtended
     */
    public function handle(object $event): void
    {
        $this->notifier->extended(
            EventPayload::instance($event, 'competition', Competition::class),
            EventPayload::instance($event, 'extension', CompetitionExtension::class),
        );
    }
}
