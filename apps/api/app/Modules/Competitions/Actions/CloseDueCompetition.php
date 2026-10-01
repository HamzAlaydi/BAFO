<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Bidding\Contracts\BiddingEngine;
use App\Modules\Competitions\Contracts\CompetitionStateMachine;
use App\Modules\Competitions\Data\CloseAttempt;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Events\CompetitionClosed;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\InvitationExpirer;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Clock\DbClock;
use Illuminate\Support\Facades\DB;

/**
 * T5 live → closed at `effective_close_at` (ARCHITECTURE §7.8), run by the CloseCompetition job
 * and by ForceCloseCompetition. It takes the same row lock as offer acceptance, so an offer is
 * valid only if it was accepted before the close read under that lock.
 */
final readonly class CloseDueCompetition
{
    public function __construct(
        private DbClock $clock,
        private CompetitionStateMachine $stateMachine,
        private BiddingEngine $bidding,
        private InvitationExpirer $invitations,
    ) {}

    public function handle(int $competitionId, ?Actor $actor = null): CloseAttempt
    {
        $actor ??= Actor::system();

        return DB::transaction(function () use ($competitionId, $actor): CloseAttempt {
            $competition = Competition::query()->whereKey($competitionId)->lockForUpdate()->first();
            $now = $this->clock->now();

            if ($competition === null || $competition->status !== CompetitionStatus::Live) {
                return new CloseAttempt(closed: false);
            }

            $closeAt = $competition->effective_close_at;

            if ($closeAt !== null && $now->lessThan($closeAt)) {
                return new CloseAttempt(closed: false, notDueUntil: $closeAt);
            }

            // Sealed offers are unlocked at the close; the engine sees the time on the model.
            if ($competition->isSealed()) {
                $competition->offers_opened_at = $now;
            }

            $this->bidding->finalizeLiveBidding($competition, $now);

            $this->stateMachine->transition($competition, CompetitionStatus::Closed, $actor, [
                'closed_at' => $closeAt ?? $now,
                'offers_opened_at' => $competition->isSealed() ? $now : null,
            ]);

            $this->invitations->expire($competition, $now);

            AuditLogger::log('competition.closed', $competition, actor: $actor, organizationId: $competition->organization_id);

            event(new CompetitionClosed($competition));

            return new CloseAttempt(closed: true);
        });
    }
}
