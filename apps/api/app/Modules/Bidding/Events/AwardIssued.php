<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Events;

use App\Modules\Bidding\Models\Award;
use App\Modules\Competitions\Models\Competition;
use App\Support\Auth\Actor;
use Illuminate\Queue\SerializesModels;

/**
 * T10: the competition was awarded (ARCHITECTURE §7.12, §10).
 */
final readonly class AwardIssued
{
    use SerializesModels;

    public function __construct(
        public Award $award,
        public Competition $competition,
        public Actor $actor,
    ) {}
}
