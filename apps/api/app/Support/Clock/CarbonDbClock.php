<?php

declare(strict_types=1);

namespace App\Support\Clock;

use Carbon\CarbonImmutable;

/**
 * The test clock: CarbonImmutable::now() in UTC, so $this->travelTo() controls it.
 * tests/TestCase.php binds it for every feature test.
 */
final class CarbonDbClock implements DbClock
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }
}
