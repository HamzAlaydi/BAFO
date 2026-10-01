<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Services;

use App\Modules\Bidding\Data\RankingResult;
use App\Modules\Competitions\Models\Competition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * `Ranking::recompute` (ARCHITECTURE §7.6): a full re-rank of the competition's standings, run
 * by every writer while it holds the competition row lock.
 *
 * Order: `current_rank_key` ascending (best first in both directions), then the earliest
 * `current_at` (the time the participant reached that amount), then the lower `current_seq`.
 * Standings without a current offer have no rank. Only rows whose rank or leading flag moves
 * are written, and those rows are the "changed set".
 */
final class Ranking
{
    public function recompute(Competition $competition, CarbonImmutable $now): RankingResult
    {
        $previousLeader = $this->leaderId($competition->id);
        $timestamp = $now->utc()->format('Y-m-d H:i:s.uP');

        $ranked = DB::select(<<<'SQL'
            WITH ranked AS (
                SELECT ps.participant_id,
                       ROW_NUMBER() OVER (ORDER BY ps.current_rank_key ASC, ps.current_at ASC, ps.current_seq ASC) AS r
                FROM participant_standings ps
                WHERE ps.competition_id = ? AND ps.current_offer_id IS NOT NULL
            )
            UPDATE participant_standings ps
            SET rank = ranked.r, is_leader = (ranked.r = 1), updated_at = ?
            FROM ranked
            WHERE ps.participant_id = ranked.participant_id
              AND (ps.rank IS DISTINCT FROM ranked.r OR ps.is_leader IS DISTINCT FROM (ranked.r = 1))
            RETURNING ps.participant_id
            SQL, [$competition->id, $timestamp]);

        $unranked = DB::select(<<<'SQL'
            UPDATE participant_standings
            SET rank = NULL, is_leader = false, updated_at = ?
            WHERE competition_id = ? AND current_offer_id IS NULL AND (rank IS NOT NULL OR is_leader)
            RETURNING participant_id
            SQL, [$timestamp, $competition->id]);

        $changed = [];

        foreach ([...$ranked, ...$unranked] as $row) {
            $changed[] = (int) $row->participant_id;
        }

        $leader = $this->leaderId($competition->id);
        $leaderChanged = $leader !== $previousLeader;

        return new RankingResult(
            leaderChanged: $leaderChanged,
            previousLeaderParticipantId: $leaderChanged ? $previousLeader : null,
            leaderParticipantId: $leader,
            changedParticipantIds: array_values(array_unique($changed)),
        );
    }

    private function leaderId(int $competitionId): ?int
    {
        $id = DB::table('participant_standings')
            ->where('competition_id', $competitionId)
            ->where('is_leader', true)
            ->value('participant_id');

        return $id === null ? null : (int) $id;
    }
}
