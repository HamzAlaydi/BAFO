<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Contracts;

use App\Modules\Competitions\Data\Viewer;
use App\Modules\Competitions\Models\Competition;
use Carbon\CarbonImmutable;

/**
 * The Bidding side of the competition lifecycle (ARCHITECTURE §3.6). Bound as a singleton by
 * BiddingServiceProvider (`Services\BiddingEngineService`).
 */
interface BiddingEngine
{
    /**
     * Inside the close transaction (competition row locked, status still live): the final
     * ranking, the live-state version bump and the sealed-unlock bookkeeping. For a sealed
     * competition the caller sets `offers_opened_at` on the locked model first; the engine then
     * dispatches `OffersUnsealed`.
     */
    public function finalizeLiveBidding(Competition $locked, CarbonImmutable $now): void;

    /**
     * Projected live snapshot for the viewer (§7.9): `IssuerLiveSnapshot` for the issuer and its
     * API clients, `ParticipantLiveSnapshot` for a participant; null when the viewer has no live
     * view (invitees, drafts).
     *
     * @return array<string, mixed>|null
     */
    public function snapshotFor(Competition $c, Viewer $viewer): ?array;

    /**
     * Issuer-visible leading amount (null when sealed and not yet unlocked, or no offers).
     */
    public function issuerLeadingAmount(Competition $c): ?int;

    public function participantsWithOffersCount(Competition $c): int;
}
