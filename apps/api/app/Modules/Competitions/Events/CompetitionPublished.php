<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\Competition;
use App\Support\Auth\Actor;

/**
 * T1 or T2: a draft was published (ARCHITECTURE §6.1, §10).
 */
final readonly class CompetitionPublished
{
    public function __construct(
        public Competition $competition,
        public Actor $actor,
    ) {}
}
