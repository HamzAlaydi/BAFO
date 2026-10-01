<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use App\Support\Auth\Actor;

/**
 * Dispatched by CompetitionStateMachine::transition() for every status change (ARCHITECTURE §6.1, §10).
 */
final readonly class CompetitionStatusChanged
{
    public function __construct(
        public Competition $competition,
        public CompetitionStatus $from,
        public CompetitionStatus $to,
        public Actor $actor,
    ) {}
}
