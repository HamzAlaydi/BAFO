<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Listeners;

use App\Modules\Bidding\Actions\FinishBafoRound;
use App\Modules\Bidding\Enums\BafoRoundStatus;
use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Competitions\Events\CompetitionCancelled;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * T9 (bafo_round → cancelled, ARCHITECTURE §6.1): ends the running BAFO round at once instead
 * of leaving it `running` until its delayed EndBafoRound job reaches the cutoff. FinishBafoRound
 * is idempotent and ends a round whose competition is no longer in `bafo_round` without a
 * transition.
 */
final class EndCancelledBafoRound implements ShouldHandleEventsAfterCommit, ShouldQueueAfterCommit
{
    public string $queue = 'live';

    public function __construct(private readonly FinishBafoRound $finish) {}

    public function handle(CompetitionCancelled $event): void
    {
        $running = BafoRound::query()
            ->where('competition_id', $event->competition->id)
            ->where('status', BafoRoundStatus::Running->value)
            ->exists();

        if ($running) {
            $this->finish->handle($event->competition->id);
        }
    }
}
