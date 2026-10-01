<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\Competition;
use App\Support\Auth\Actor;

/**
 * A draft was created (ARCHITECTURE §10).
 */
final readonly class CompetitionCreated
{
    public function __construct(
        public Competition $competition,
        public Actor $actor,
    ) {}
}
