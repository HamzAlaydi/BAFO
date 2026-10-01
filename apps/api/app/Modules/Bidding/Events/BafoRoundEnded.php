<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Events;

use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Competitions\Models\Competition;
use Illuminate\Queue\SerializesModels;

/**
 * T8: the BAFO cutoff passed; the competition is back in evaluation (ARCHITECTURE §7.11, §10).
 */
final readonly class BafoRoundEnded
{
    use SerializesModels;

    public function __construct(
        public BafoRound $round,
        public Competition $competition,
    ) {}
}
