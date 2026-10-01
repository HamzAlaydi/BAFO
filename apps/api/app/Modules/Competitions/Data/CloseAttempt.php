<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Data;

use Carbon\CarbonImmutable;

/**
 * The outcome of `CloseDueCompetition::handle()`: closed now, not due yet (retry at
 * `$notDueUntil`), or nothing to do (not live any more).
 */
final readonly class CloseAttempt
{
    public function __construct(
        public bool $closed,
        public ?CarbonImmutable $notDueUntil = null,
    ) {}
}
