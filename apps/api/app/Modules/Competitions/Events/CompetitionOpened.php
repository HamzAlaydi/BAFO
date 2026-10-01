<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\Competition;

/**
 * Bidding opened: T2 (publish with an opening time now or earlier) or T3 (tick) (ARCHITECTURE §10).
 */
final readonly class CompetitionOpened
{
    public function __construct(
        public Competition $competition,
    ) {}
}
