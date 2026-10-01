<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\Competition;
use App\Support\Auth\Actor;

/**
 * T4, T6 or T9 (ARCHITECTURE §6.1, §10).
 */
final readonly class CompetitionCancelled
{
    public function __construct(
        public Competition $competition,
        public Actor $actor,
    ) {}
}
