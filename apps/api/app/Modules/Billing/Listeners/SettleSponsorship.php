<?php

declare(strict_types=1);

namespace App\Modules\Billing\Listeners;

use App\Modules\Billing\Actions\SettleCompetitionSponsorship;
use App\Modules\Competitions\Events\CompetitionCancelled;
use App\Modules\Competitions\Events\CompetitionClosed;
use App\Support\Auth\Actor;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Queued listener (ARCHITECTURE §10, queue `billing`, after commit) on the Competitions events
 * CompetitionClosed and CompetitionCancelled: settles the competition's sponsorship
 * (SettleCompetitionSponsorship; idempotent, so a cancel after a close changes nothing).
 */
final class SettleSponsorship implements ShouldHandleEventsAfterCommit, ShouldQueueAfterCommit
{
    public string $queue = 'billing';

    public int $tries = 3;

    public function __construct(private readonly SettleCompetitionSponsorship $settle) {}

    public function handle(CompetitionClosed|CompetitionCancelled $event): void
    {
        $this->settle->handle($event->competition, Actor::system());
    }
}
