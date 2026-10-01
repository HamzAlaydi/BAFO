<?php

declare(strict_types=1);

use App\Modules\Bidding\Services\OfferRules;
use App\Modules\Competitions\Enums\Direction;
use App\Modules\Competitions\Models\Competition;
use Tests\TestCase;

uses(TestCase::class);

/*
 * The amount arithmetic of ARCHITECTURE §7.1 and §7.5, without the database.
 */

function rulesCompetition(Direction $direction, ?int $stepMinor = null, ?int $stepBps = null, int $granularity = 100, ?int $reserve = null): Competition
{
    return (new Competition)->forceFill([
        'direction' => $direction,
        'min_step_minor' => $stepMinor,
        'min_step_bps' => $stepBps,
        'amount_granularity_minor' => $granularity,
        'reserve_price_minor' => $reserve,
    ]);
}

it('compares amounts through the direction sign only', function () {
    $tender = rulesCompetition(Direction::Tender);
    $auction = rulesCompetition(Direction::Auction);

    expect(OfferRules::compare($tender, 900, 1000))->toBeGreaterThan(0)      // lower is better in a tender
        ->and(OfferRules::compare($auction, 1100, 1000))->toBeGreaterThan(0) // higher is better in an auction
        ->and(OfferRules::isWorse($tender, 1100, 1000))->toBeTrue()
        ->and(OfferRules::isWorse($auction, 900, 1000))->toBeTrue()
        ->and(OfferRules::isWorse($tender, 1000, 1000))->toBeFalse()
        ->and(OfferRules::rankKey($tender, 1000))->toBe(1000)
        ->and(OfferRules::rankKey($auction, 1000))->toBe(-1000);
});

it('uses the absolute step when it is set', function (Direction $direction, int $reference, int $expected) {
    $competition = rulesCompetition($direction, stepMinor: 50_000);

    expect(OfferRules::step($competition, $reference))->toBe(50_000)
        ->and(OfferRules::requiredAmount($competition, $reference))->toBe($expected);
})->with([
    'tender' => [Direction::Tender, 10_000_000, 9_950_000],
    'auction' => [Direction::Auction, 10_000_000, 10_050_000],
]);

it('rounds the percentage step up to the granularity', function (int $reference, int $bps, int $granularity, int $step) {
    $competition = rulesCompetition(Direction::Tender, stepBps: $bps, granularity: $granularity);

    expect(OfferRules::step($competition, $reference))->toBe($step);
})->with([
    // ceil(9 850 000 × 50 / 10000) = 49 250 → whole riyals: 49 300
    'whole riyals' => [9_850_000, 50, 100, 49_300],
    // halalas: ceil(9 850 001 × 50 / 10000) = ceil(49 250.005) = 49 251
    'halalas' => [9_850_001, 50, 1, 49_251],
    // a tiny reference still moves by one unit: ceil(1000 × 1 / 10000) = 1 → max(100, 100)
    'at least one unit' => [1_000, 1, 100, 100],
    // exact multiples are not rounded
    'exact' => [10_000_000, 50, 100, 50_000],
]);

it('falls back to one granularity unit without a step rule', function (int $granularity) {
    $competition = rulesCompetition(Direction::Auction, granularity: $granularity);

    expect(OfferRules::step($competition, 123_400))->toBe($granularity)
        ->and(OfferRules::requiredAmount($competition, 123_400))->toBe(123_400 + $granularity);
})->with([1, 100]);

it('guards outliers exactly in integers', function () {
    // 20% of 10 000 000 = 2 000 000: exactly 20% is allowed, one halala more is not.
    expect(OfferRules::exceedsOutlierGuard(8_000_000, 10_000_000, 2000))->toBeFalse()
        ->and(OfferRules::exceedsOutlierGuard(7_999_999, 10_000_000, 2000))->toBeTrue()
        ->and(OfferRules::exceedsOutlierGuard(12_000_001, 10_000_000, 2000))->toBeTrue()
        ->and(OfferRules::changeBps(7_500_000, 10_000_000))->toBe(2500);
});

it('computes improvement ratios in the direction of the competition', function () {
    $tender = rulesCompetition(Direction::Tender);
    $auction = rulesCompetition(Direction::Auction);

    // Savings for a tender, uplift for an auction: positive is good in both.
    expect(OfferRules::improvementBps($tender, 9_392_000, 10_000_000))->toBe(608)
        ->and(OfferRules::improvementBps($auction, 10_608_000, 10_000_000))->toBe(608)
        ->and(OfferRules::improvementBps($tender, 10_500_000, 10_000_000))->toBe(-500)
        ->and(OfferRules::improvementBps($tender, null, 10_000_000))->toBeNull()
        ->and(OfferRules::improvementBps($tender, 1, null))->toBeNull();
});
