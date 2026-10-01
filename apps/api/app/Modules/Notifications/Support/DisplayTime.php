<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Date and time as users read them in notifications (CONVENTIONS §9.1): Asia/Riyadh, Gregorian,
 * Western digits, 12-hour clock. Arabic «9 نوفمبر 2026، 3:30 م», English "9 Nov 2026, 3:30 PM".
 * The words come from `notifications.time.*`, so the output does not depend on the ICU data
 * installed on the server.
 */
final class DisplayTime
{
    public const string ZONE = 'Asia/Riyadh';

    public static function format(CarbonInterface $at, string $locale): string
    {
        $local = CarbonImmutable::instance($at)->setTimezone(self::ZONE);

        return $local->day.' '.self::word('months.'.$local->month, $locale).' '.$local->year
            .self::word('separator', $locale)
            .$local->format('g:i').' '.self::word($local->hour < 12 ? 'am' : 'pm', $locale);
    }

    /**
     * An ISO-8601 string (as stored in notification parameters); unparsable input is returned unchanged.
     */
    public static function formatIso(string $iso, string $locale): string
    {
        try {
            return self::format(CarbonImmutable::parse($iso), $locale);
        } catch (\Throwable) {
            return $iso;
        }
    }

    private static function word(string $key, string $locale): string
    {
        $word = __('notifications.time.'.$key, [], $locale);

        return is_string($word) ? $word : '';
    }
}
