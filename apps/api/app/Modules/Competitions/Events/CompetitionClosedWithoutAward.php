<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\Competition;
use App\Support\Auth\Actor;

/**
 * T12: closed → not_awarded (ARCHITECTURE §7.12, §10).
 */
final readonly class CompetitionClosedWithoutAward
{
    public function __construct(
        public Competition $competition,
        public Actor $actor,
    ) {}
}
