<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Jobs;

use App\Modules\Bidding\Actions\FinishBafoRound;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Ends a BAFO round at its cutoff (ARCHITECTURE §7.11, T8), on queue `live`. Dispatched after
 * commit by StartBafoRound with the cutoff as its delay; `bidding:tick` dispatches it again for
 * every running round that is due. Idempotent: FinishBafoRound re-reads the round under the
 * competition lock; when the round is not due yet the job schedules itself again.
 *
 * Unique per competition for a short time, so the tick does not pile up copies.
 */
final class EndBafoRound implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $uniqueFor;

    public function __construct(public readonly int $competitionId)
    {
        $this->onQueue('live');
        $this->uniqueFor = (int) config('bafo.bidding.end_bafo_unique_seconds', 30);
    }

    public function uniqueId(): string
    {
        return (string) $this->competitionId;
    }

    public function handle(FinishBafoRound $finish): void
    {
        $notDueUntil = $finish->handle($this->competitionId);

        if ($notDueUntil !== null && $notDueUntil->isFuture()) {
            self::dispatch($this->competitionId)->delay($notDueUntil);
        }
    }
}
