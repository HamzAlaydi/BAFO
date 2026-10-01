<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

/**
 * The catalogue throttle windows (ARCHITECTURE §11.3), from `bafo.notifications.throttle.*`.
 */
final class Throttles
{
    private const array DEFAULTS = [
        'competition_updated' => 600,
        'competition_extended_auto' => 120,
        'offer_received' => 300,
        'standing_lost_lead' => 60,
        'comment_created_push' => 300,
    ];

    public static function seconds(string $name): int
    {
        $value = config('bafo.notifications.throttle.'.$name);

        return is_int($value) ? $value : self::DEFAULTS[$name] ?? 60;
    }
}
