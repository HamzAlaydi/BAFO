<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Events;

use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Competitions\Models\Competition;
use App\Support\Auth\Actor;
use Illuminate\Queue\SerializesModels;

/**
 * T7: the issuer invited a shortlist to one best-and-final offer (ARCHITECTURE §7.11, §10).
 */
final readonly class BafoRoundStarted
{
    use SerializesModels;

    public function __construct(
        public BafoRound $round,
        public Competition $competition,
        public Actor $actor,
    ) {}
}
