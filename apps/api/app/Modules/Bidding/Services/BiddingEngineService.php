<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Services;

use App\Modules\Bidding\Contracts\BiddingEngine;
use App\Modules\Bidding\Events\OffersUnsealed;
use App\Modules\Competitions\Data\Viewer;
use App\Modules\Competitions\Models\Competition;
use Carbon\CarbonImmutable;

/**
 * The `BiddingEngine` contract (ARCHITECTURE §3.6) used by Competitions for the close
 * transaction and the live values of competition payloads.
 */
final readonly class BiddingEngineService implements BiddingEngine
{
    public function __construct(
        private Ranking $ranking,
        private LiveStateManager $liveStates,
        private VisibilityProjector $projector,
    ) {}

    /**
     * §7.8: final re-rank, live state (leader, reserve, counts) and version++ in the close
     * transaction. For a sealed competition the offers are unlocked: `OffersUnsealed`.
     */
    public function finalizeLiveBidding(Competition $locked, CarbonImmutable $now): void
    {
        $this->ranking->recompute($locked, $now);

        $state = $this->liveStates->forLocked($locked);
        $this->liveStates->syncFromStandings($locked, $state);
        $state->version++;
        $state->save();

        if ($locked->isSealed()) {
            // CONTRACT-GAP: §7.8 lists OffersUnsealed among the close events without naming the
            // dispatcher. The close sets `offers_opened_at` on the locked model before calling
            // the engine, so the engine dispatches it here, once, inside the close transaction.
            $locked->offers_opened_at ??= $now;

            event(new OffersUnsealed($locked));
        }
    }

    public function snapshotFor(Competition $c, Viewer $viewer): ?array
    {
        if ($c->isDraft()) {
            return null;
        }

        if ($viewer->isIssuer()) {
            return $this->projector->issuerSnapshot($c);
        }

        if ($viewer->isParticipant() && $viewer->participant !== null) {
            return $this->projector->participantSnapshot($c, $viewer->participant);
        }

        return null;
    }

    public function issuerLeadingAmount(Competition $c): ?int
    {
        return $this->projector->issuerLeadingAmount($c);
    }

    public function participantsWithOffersCount(Competition $c): int
    {
        return $this->liveStates->read($c)->participants_with_offers;
    }
}
