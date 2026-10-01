<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\Competition;

/**
 * The final pricing window of a live competition started (tick, ARCHITECTURE §12).
 */
final readonly class CompetitionFinalWindowStarted
{
    public function __construct(
        public Competition $competition,
    ) {}
}
