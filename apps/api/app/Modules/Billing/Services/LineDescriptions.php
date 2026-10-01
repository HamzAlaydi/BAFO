<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Models\Plan;
use App\Modules\Competitions\Models\Competition;

/**
 * The `{ar, en}` descriptions stored on payment lines and copied to invoice lines
 * (ARCHITECTURE §5.7 `payment_lines.description`).
 */
final class LineDescriptions
{
    /**
     * @return array{ar: string, en: string}
     */
    public static function plan(Plan $plan, BillingInterval $interval, int $seats): array
    {
        return self::both(static fn (string $locale): string => self::text(
            $plan->is_custom ? 'billing.lines.custom_seats' : 'billing.lines.plan',
            [
                'plan' => $plan->translated('name', $locale) ?? $plan->code,
                'interval' => $interval->label($locale),
                'seats' => $seats,
            ],
            $locale,
        ));
    }

    /**
     * @return array{ar: string, en: string}
     */
    public static function sponsoredPass(Competition $competition): array
    {
        return self::both(static fn (string $locale): string => self::text(
            'billing.lines.sponsored_pass',
            ['reference' => $competition->reference_no ?? $competition->title],
            $locale,
        ));
    }

    /**
     * @param  callable(string): string  $make
     * @return array{ar: string, en: string}
     */
    private static function both(callable $make): array
    {
        return ['ar' => $make('ar'), 'en' => $make('en')];
    }

    /**
     * @param  array<string, scalar>  $replace
     */
    private static function text(string $key, array $replace, string $locale): string
    {
        $text = __($key, $replace, $locale);

        return is_string($text) ? $text : $key;
    }
}
