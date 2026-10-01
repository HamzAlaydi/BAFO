<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Services;

use App\Modules\Bidding\Models\CompetitionLiveState;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Competitions\Models\Competition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The `competition_live_states` row (ARCHITECTURE §5.6): created lazily under the competition
 * lock, its `version` moves on every audience-visible change (§9.3).
 *
 * Writers inside the engine (offers, voids, BAFO, award) change the row in their own
 * transaction, after locking the competition. Lifecycle events coming from Competitions use
 * the atomic `bumpVersion()` instead (§7.10).
 */
final class LiveStateManager
{
    /**
     * The row, created when missing, and locked. The caller holds `SELECT … FOR UPDATE` on the
     * competition; the row lock also serialises it with `bumpVersion()`, which lifecycle listeners
     * run without the competition lock. Without it a bump committing between this read and the
     * caller's save was overwritten, and two different snapshots went out with the same `v`
     * (clients keep the first and drop the other).
     */
    public function forLocked(Competition $locked): CompetitionLiveState
    {
        $state = CompetitionLiveState::query()->whereKey($locked->id)->lockForUpdate()->first();

        if ($state !== null) {
            return $state;
        }

        CompetitionLiveState::query()->createOrFirst(['competition_id' => $locked->id]);

        return CompetitionLiveState::query()->whereKey($locked->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * The stored row for reading, or an unsaved one at version 0 (reads never create it).
     */
    public function read(Competition $competition): CompetitionLiveState
    {
        if ($competition->relationLoaded('liveState') && $competition->liveState !== null) {
            return $competition->liveState;
        }

        return CompetitionLiveState::query()->find($competition->id)
            ?? new CompetitionLiveState(['competition_id' => $competition->id]);
    }

    /**
     * Copies the leader (rank 1), the reserve status and the participants-with-offers count
     * from the standings onto the state (§7.6 "Live state"). Not saved.
     */
    public function syncFromStandings(Competition $competition, CompetitionLiveState $state): ?ParticipantStanding
    {
        $leader = ParticipantStanding::query()
            ->where('competition_id', $competition->id)
            ->where('is_leader', true)
            ->first();

        $state->leader_participant_id = $leader?->participant_id;
        $state->leader_offer_id = $leader?->current_offer_id;
        $state->leader_amount_minor = $leader?->current_amount_minor;
        $state->reserve_met = self::reserveMet($competition, $leader?->current_amount_minor);
        $state->participants_with_offers = ParticipantStanding::query()
            ->where('competition_id', $competition->id)
            ->whereNotNull('current_offer_id')
            ->count();

        return $leader;
    }

    /**
     * Recounts the non-voided offers (after a void). Not saved.
     */
    public function recountOffers(Competition $competition, CompetitionLiveState $state): void
    {
        $state->accepted_offer_count = Offer::query()
            ->where('competition_id', $competition->id)
            ->whereDoesntHave('void')
            ->count();
    }

    /**
     * `d × (amount − reserve) ≥ 0`; null without a reserve or without an amount.
     */
    public static function reserveMet(Competition $competition, ?int $amount): ?bool
    {
        if ($competition->reserve_price_minor === null || $amount === null) {
            return null;
        }

        return OfferRules::compare($competition, $amount, $competition->reserve_price_minor) >= 0;
    }

    /**
     * Atomic `version + 1` (creating the row at version 1 when missing), for changes that come
     * from outside the engine's transactions: `UPDATE … SET version = version + 1 RETURNING`.
     */
    public function bumpVersion(int $competitionId): int
    {
        $row = DB::selectOne(<<<'SQL'
            INSERT INTO competition_live_states (competition_id, version, last_seq, accepted_offer_count, participants_with_offers, updated_at)
            VALUES (?, 1, 0, 0, 0, ?)
            ON CONFLICT (competition_id) DO UPDATE
                SET version = competition_live_states.version + 1, updated_at = EXCLUDED.updated_at
            RETURNING version
            SQL, [$competitionId, CarbonImmutable::now()->utc()->format('Y-m-d H:i:s.uP')]);

        return is_object($row) && isset($row->version) ? (int) $row->version : 0;
    }
}
