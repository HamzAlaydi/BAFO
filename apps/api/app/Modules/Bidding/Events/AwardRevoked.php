<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Events;

use App\Modules\Bidding\Models\Award;
use App\Modules\Competitions\Models\Competition;
use App\Support\Auth\Actor;
use Illuminate\Queue\SerializesModels;

/**
 * T11: the issuer revoked the award; the competition is back in evaluation (ARCHITECTURE §7.12, §10).
 */
final readonly class AwardRevoked
{
    use SerializesModels;

    public function __construct(
        public Award $award,
        public Competition $competition,
        public Actor $actor,
    ) {}
}
