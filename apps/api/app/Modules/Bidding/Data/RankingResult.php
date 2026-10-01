<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Data;

/**
 * The outcome of `Ranking::recompute()` (ARCHITECTURE §7.6): whether rank 1 changed hands, who
 * led before, who leads now, and every participant whose rank or leading flag changed.
 */
final readonly class RankingResult
{
    /**
     * @param  list<int>  $changedParticipantIds  internal participant ids
     */
    public function __construct(
        public bool $leaderChanged,
        public ?int $previousLeaderParticipantId,
        public ?int $leaderParticipantId,
        public array $changedParticipantIds,
    ) {}
}
