<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionExtension;
use App\Support\Auth\Actor;

/**
 * `effective_close_at` moved: auto (anti-sniping), manual or admin (ARCHITECTURE §7.7, §7.17, §10).
 */
final readonly class CompetitionExtended
{
    public function __construct(
        public Competition $competition,
        public CompetitionExtension $extension,
        public Actor $actor,
    ) {}
}
