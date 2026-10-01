<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Actions;

use App\Modules\Bidding\Enums\BafoRoundStatus;
use App\Modules\Bidding\Events\BafoRoundEnded;
use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Bidding\Services\LiveStateManager;
use App\Modules\Bidding\Services\Ranking;
use App\Modules\Competitions\Contracts\CompetitionStateMachine;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Clock\DbClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * T8 bafo_round → closed at the cutoff (ARCHITECTURE §7.11 "End", §6.1 `EndDueBafoRounds`), run
 * by the EndBafoRound job and the `bidding:tick` safety net. Idempotent: under the competition
 * lock it ends a running round only when the DB clock has reached `cutoff_at`.
 *
 * Returns the cutoff when the round is not due yet (the job re-dispatches itself), else null.
 */
final readonly class FinishBafoRound
{
    public function __construct(
        private DbClock $clock,
        private CompetitionStateMachine $stateMachine,
        private Ranking $ranking,
        private LiveStateManager $liveStates,
    ) {}

    public function handle(int $competitionId): ?CarbonImmutable
    {
        return DB::transaction(function () use ($competitionId): ?CarbonImmutable {
            $locked = Competition::query()->whereKey($competitionId)->lockForUpdate()->first();
            $now = $this->clock->now();
            $round = $locked !== null ? BafoRound::query()->where('competition_id', $locked->id)->first() : null;

            if ($locked === null || $round === null || ! $round->isRunning()) {
                return null;
            }

            // Cancelled during the round (T9): the round simply ends, without a transition.
            if ($locked->status !== CompetitionStatus::BafoRound) {
                $round->forceFill(['status' => BafoRoundStatus::Ended, 'ended_at' => $now])->save();

                return null;
            }

            if ($now->lessThan($round->cutoff_at)) {
                return $round->cutoff_at;
            }

            $actor = Actor::system();
            $round->forceFill(['status' => BafoRoundStatus::Ended, 'ended_at' => $now])->save();

            $this->ranking->recompute($locked, $now);
            $this->stateMachine->transition($locked, CompetitionStatus::Closed, $actor);

            $state = $this->liveStates->forLocked($locked);
            $this->liveStates->syncFromStandings($locked, $state);
            $state->version++;
            $state->save();

            AuditLogger::log('bafo_round.ended', $round, actor: $actor, organizationId: $locked->organization_id);

            event(new BafoRoundEnded($round, $locked));

            return null;
        });
    }
}
