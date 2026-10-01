<?php

declare(strict_types=1);

namespace App\Support\Clock;

use Carbon\CarbonImmutable;

/**
 * The database clock (ARCHITECTURE §4.2). Only the bidding engine, close, BAFO and award code
 * use it; everything else reads now().
 *
 * Production binds PostgresDbClock (`clock_timestamp()`, so inside a transaction it is the
 * time after the row lock). Tests bind CarbonDbClock, which honours $this->travelTo().
 */
interface DbClock
{
    public function now(): CarbonImmutable;
}
