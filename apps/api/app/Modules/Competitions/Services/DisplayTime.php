<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use Carbon\CarbonInterface;

/**
 * Human date and time in Asia/Riyadh for server-rendered text (mails, rules summary), following
 * CONVENTIONS §9.1: Gregorian, Western digits, 12-hour clock.
 *
 *   ar  «9 نوفمبر 2026، 3:00 م»
 *   en  "9 Nov 2026, 3:00 PM"
 */
final class DisplayTime
{
    private const array ARABIC_MONTHS = [
        1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
    ];

    public static function format(CarbonInterface $time, string $locale): string
    {
        $local = $time->copy()->setTimezone('Asia/Riyadh');

        if ($locale === 'ar') {
            return sprintf(
                '%d %s %d، %s %s',
                $local->day,
                self::ARABIC_MONTHS[$local->month],
                $local->year,
                $local->format('g:i'),
                $local->hour < 12 ? 'ص' : 'م',
            );
        }

        return $local->format('j M Y, g:i A');
    }
}
