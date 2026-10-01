<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Jobs;

use App\Modules\Competitions\Actions\CloseDueCompetition;
use App\Modules\Competitions\Models\Competition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Closes a live competition at `effective_close_at` (ARCHITECTURE §7.8, T5). Dispatched after
 * commit at publish and after each extension with the close time as its delay; the
 * `competitions:tick` safety net dispatches it again for every live competition that is due.
 *
 * Idempotent: the Action re-reads the row under `FOR UPDATE` and does nothing unless the
 * competition is still live and due. When it is not due yet (an extension moved the close), the
 * job schedules itself again.
 *
 * Unique per competition for one minute, so the tick does not pile up copies while a close is
 * queued; the short lock also lets a later extension schedule a fresh delayed job.
 */
final class CloseCompetition implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $uniqueFor = 60;

    public function __construct(public readonly int $competitionId)
    {
        $this->onQueue('live');
    }

    /**
     * Dispatch after the surrounding transaction commits, delayed to the competition's close.
     */
    public static function scheduleFor(Competition $competition): void
    {
        $closeAt = $competition->effective_close_at;

        $pending = self::dispatch($competition->id)->afterCommit();

        if ($closeAt !== null && $closeAt->isFuture()) {
            $pending->delay($closeAt);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->competitionId;
    }

    public function handle(CloseDueCompetition $close): void
    {
        $result = $close->handle($this->competitionId);

        if ($result->notDueUntil !== null) {
            self::dispatch($this->competitionId)->delay($result->notDueUntil);
        }
    }
}
