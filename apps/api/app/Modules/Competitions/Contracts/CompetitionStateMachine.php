<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Contracts;

use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;

/**
 * The only writer of `competitions.status` (ARCHITECTURE §3.6, §6.1).
 *
 * Bound as a singleton by CompetitionsServiceProvider. Bidding calls it for T7 (closed →
 * bafo_round), T8 (bafo_round → closed), T10 (closed → awarded) and T11 (awarded → closed).
 */
interface CompetitionStateMachine
{
    /**
     * Validates the transition against §6.1, sets the status and the matching lifecycle column
     * (published_at, opened_at, closed_at, awarded_at, not_awarded_at, cancelled_at) unless
     * `$attributes` sets it, fills the other `$attributes`, saves, and dispatches
     * CompetitionStatusChanged. The caller holds `SELECT … FOR UPDATE` on the row, runs inside
     * a transaction and writes its own audit entry.
     *
     * Leaving `awarded` (T11) also clears `awarded_at`.
     *
     * @param  array<string, mixed>  $attributes  extra competition columns written in the same save
     *
     * @throws ApiException invalid_state_transition (409, details {from, to})
     */
    public function transition(Competition $locked, CompetitionStatus $to, Actor $actor, array $attributes = []): void;

    /**
     * Whether §6.1 has a transition from `$from` to `$to`.
     *
     * CONTRACT-GAP: not in the §3.6 signature; a read-only helper for callers that need to know
     * whether an action is possible before they take the lock.
     */
    public function allows(CompetitionStatus $from, CompetitionStatus $to): bool;
}
