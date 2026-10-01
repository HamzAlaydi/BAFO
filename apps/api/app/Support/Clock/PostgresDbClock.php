<?php

declare(strict_types=1);

namespace App\Support\Clock;

use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use RuntimeException;

/**
 * Reads `clock_timestamp()` on the current connection: the wall-clock time at the moment of
 * the query (not the transaction start), with microseconds, in UTC.
 */
final readonly class PostgresDbClock implements DbClock
{
    public function __construct(private DatabaseManager $database) {}

    public function now(): CarbonImmutable
    {
        $row = $this->database->connection()->selectOne('select clock_timestamp() as now');
        $value = is_object($row) ? ($row->now ?? null) : null;

        if (! is_string($value)) {
            throw new RuntimeException('The database did not return clock_timestamp().');
        }

        return CarbonImmutable::parse($value)->utc();
    }
}
