<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Services;

use App\Modules\Competitions\Models\Competition;
use App\Support\Money\Money;

/**
 * The amount arithmetic of the engine (ARCHITECTURE §7.1 and §7.5). Pure functions of the
 * competition's rule columns; every comparison goes through the direction sign `d`
 * (tender −1, auction +1), never through "lowest" or "highest".
 */
final class OfferRules
{
    /**
     * `d × (a − b)`: positive when `a` is better than `b`, zero when equal, negative when worse.
     */
    public static function compare(Competition $competition, int $a, int $b): int
    {
        return $competition->direction->sign() * ($a - $b);
    }

    public static function isWorse(Competition $competition, int $amount, int $bound): bool
    {
        return self::compare($competition, $amount, $bound) < 0;
    }

    /**
     * The minimum improvement over the reference amount (§7.5):
     *
     *   min_step_minor                                                     when set
     *   max(granularity, ceilTo(ceil(ref × min_step_bps / 10000), granularity))   when min_step_bps is set
     *   granularity                                                        otherwise
     *
     * It is never below the granularity, so an offer always improves by at least one unit.
     */
    public static function step(Competition $competition, int $reference): int
    {
        $granularity = max(1, $competition->amount_granularity_minor);

        if ($competition->min_step_minor !== null) {
            return max($granularity, $competition->min_step_minor);
        }

        if ($competition->min_step_bps !== null) {
            // ceil(ref × bps / 10000) for a positive reference, in integers.
            $raw = intdiv($reference * $competition->min_step_bps + 9999, 10000);

            return max($granularity, Money::ceilTo($raw, $granularity));
        }

        return $granularity;
    }

    /**
     * The bound an offer must reach: `ref + d × step(ref)`. Tender → the offer must be ≤ it;
     * auction → ≥ it.
     */
    public static function requiredAmount(Competition $competition, int $reference): int
    {
        return $reference + $competition->direction->sign() * self::step($competition, $reference);
    }

    /**
     * `|amount − reference| × 10000 / reference`, truncated (the outlier guard, §7.4 step 15).
     */
    public static function changeBps(int $amount, int $reference): int
    {
        return Money::bps(abs($amount - $reference), $reference);
    }

    /**
     * True when the change exceeds the threshold, compared exactly in integers:
     * |amount − ref| × 10000 > threshold × ref.
     */
    public static function exceedsOutlierGuard(int $amount, int $reference, int $thresholdBps): bool
    {
        return abs($amount - $reference) * 10000 > $thresholdBps * $reference;
    }

    /**
     * `rank_key = −d × amount`: ascending is best first.
     */
    public static function rankKey(Competition $competition, int $amount): int
    {
        return -$competition->direction->sign() * $amount;
    }

    /**
     * `d × (a − b) × 10000 / b`, truncated toward zero; null without a base (§7.15).
     */
    public static function improvementBps(Competition $competition, ?int $amount, ?int $base): ?int
    {
        if ($amount === null || $base === null || $base <= 0) {
            return null;
        }

        return Money::bps(self::compare($competition, $amount, $base), $base);
    }
}
