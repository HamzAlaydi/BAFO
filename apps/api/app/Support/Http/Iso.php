<?php

declare(strict_types=1);

namespace App\Support\Http;

use Carbon\CarbonInterface;

/**
 * The one API timestamp format: UTC ISO-8601 with milliseconds, e.g. 2026-10-01T12:59:58.412Z.
 */
final class Iso
{
    public const string FORMAT = 'Y-m-d\TH:i:s.v\Z';

    public static function format(?CarbonInterface $time): ?string
    {
        return $time?->copy()->utc()->format(self::FORMAT);
    }

    public static function date(?CarbonInterface $time): ?string
    {
        return $time?->format('Y-m-d');
    }
}
