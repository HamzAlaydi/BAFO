<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\Competition;

/**
 * A closing-soon threshold (setting `bidding.closing_soon_minutes`) was reached (tick, ARCHITECTURE §12).
 */
final readonly class CompetitionClosingSoon
{
    public function __construct(
        public Competition $competition,
        public int $minutes,
    ) {}
}
