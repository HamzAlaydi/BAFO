<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\Competition;

/**
 * T5: bidding ended; the issuer is evaluating (ARCHITECTURE §7.8, §10).
 */
final readonly class CompetitionClosed
{
    public function __construct(
        public Competition $competition,
    ) {}
}
