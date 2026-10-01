<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Data;

use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Bidding\Models\CompetitionLiveState;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Competitions\Models\Competition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything a live projection reads, loaded once per competition so that a broadcast to many
 * participants costs a handful of queries (VisibilityProjector).
 */
final readonly class LiveView
{
    /**
     * @param  Collection<int, ParticipantStanding>  $standings  keyed by participant id, with the current offer and participant
     */
    public function __construct(
        public Competition $competition,
        public CompetitionLiveState $state,
        public Collection $standings,
        public ?BafoRound $round,
        public ?Award $issuedAward,
        public CarbonImmutable $now,
    ) {}

    public function standingOf(int $participantId): ?ParticipantStanding
    {
        return $this->standings->get($participantId);
    }

    /**
     * Standings with a rank, best first.
     *
     * @return Collection<int, ParticipantStanding>
     */
    public function ranked(): Collection
    {
        return $this->standings
            ->filter(static fn (ParticipantStanding $standing): bool => $standing->rank !== null)
            ->sortBy('rank')
            ->values();
    }
}
