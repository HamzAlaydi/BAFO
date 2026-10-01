<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Listeners;

use App\Modules\Competitions\Broadcasting\CompetitionUpdatedBroadcast;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Events\AttachmentAdded;
use App\Modules\Competitions\Events\CompetitionUpdated;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\CompetitionChannels;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * `competition.updated` for published competitions (ARCHITECTURE §10): content, schedule,
 * attachment and invitation-count changes. Drafts have no audience.
 */
final class BroadcastCompetitionUpdated implements ShouldHandleEventsAfterCommit, ShouldQueueAfterCommit
{
    public string $queue = 'live';

    public function handle(CompetitionUpdated|AttachmentAdded $event): void
    {
        [$competition, $fields] = $event instanceof AttachmentAdded
            ? [Competition::query()->find($event->attachment->competition_id), ['attachments']]
            : [$event->competition->fresh(), $event->fields];

        if ($competition === null || $competition->status === CompetitionStatus::Draft) {
            return;
        }

        broadcast(new CompetitionUpdatedBroadcast(
            $competition->public_id,
            CompetitionChannels::all($competition),
            $fields,
        ));
    }
}
