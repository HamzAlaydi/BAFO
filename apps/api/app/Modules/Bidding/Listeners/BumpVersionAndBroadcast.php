<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Listeners;

use App\Modules\Bidding\Data\LastChange;
use App\Modules\Bidding\Enums\LiveChangeKind;
use App\Modules\Bidding\Services\LiveBroadcaster;
use App\Modules\Bidding\Services\LiveStateManager;
use App\Modules\Competitions\Events\CompetitionExtended;
use App\Modules\Competitions\Events\CompetitionFinalWindowStarted;
use App\Modules\Competitions\Events\CompetitionStatusChanged;
use App\Modules\Competitions\Models\Competition;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Lifecycle changes coming from Competitions (ARCHITECTURE §7.10, §10): an atomic
 * `version + 1`, then a full snapshot to the issuer and every joined participant. Kind
 * `extension` (with the extension kind as the reason) or `status`.
 */
final class BumpVersionAndBroadcast implements ShouldHandleEventsAfterCommit, ShouldQueueAfterCommit
{
    public string $queue = 'live';

    public function __construct(
        private readonly LiveStateManager $liveStates,
        private readonly LiveBroadcaster $broadcaster,
    ) {}

    public function handle(CompetitionStatusChanged|CompetitionExtended|CompetitionFinalWindowStarted $event): void
    {
        $competition = Competition::query()->find($event->competition->id);

        if ($competition === null || $competition->isDraft()) {
            return;
        }

        $change = $event instanceof CompetitionExtended
            ? LastChange::of(LiveChangeKind::Extension, $event->extension->kind->value)
            : LastChange::of(LiveChangeKind::Status);

        $this->liveStates->bumpVersion($competition->id);

        $this->broadcaster->toEveryone($competition, $change);
    }
}
