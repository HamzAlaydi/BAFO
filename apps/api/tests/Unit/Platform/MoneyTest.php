<?php

declare(strict_types=1);

use App\Support\Money\Casts\AsMoney;
use App\Support\Money\Casts\MinorAmount;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Model;

it('computes VAT at 15% rounded half up', function (int $net, int $vat) {
    expect(Money::vat($net))->toBe($vat);
})->with([
    [0, 0],
    [100, 15],
    [11500, 1725],
    [3, 0],      // 0.45 → 0
    [10, 2],     // 1.5 → 2
    [33, 5],     // 4.95 → 5
    [1000000000000, 150000000000],
]);

it('computes VAT at another rate', function () {
    expect(Money::vat(1000, 500))->toBe(50);
});

it('rejects negative VAT inputs', function () {
    Money::vat(-1);
})->throws(InvalidArgumentException::class);

it('rounds up to a granularity', function (int $value, int $granularity, int $expected) {
    expect(Money::ceilTo($value, $granularity))->toBe($expected);
})->with([
    [9751, 50, 9800],
    [9800, 50, 9800],
    [1, 100, 100],
    [0, 100, 0],
    [-5, 3, -3],
]);

it('rejects a non-positive granularity', function () {
    Money::ceilTo(10, 0);
})->throws(InvalidArgumentException::class);

it('computes basis points truncated toward zero', function (int $part, int $whole, int $expected) {
    expect(Money::bps($part, $whole))->toBe($expected);
})->with([
    [250, 10000, 250],
    [1, 3, 3333],
    [-1, 3, -3333],
    [2000, 10000, 2000],
]);

it('formats for display as CONVENTIONS §9.2', function (int $minor, string $locale, string $expected) {
    expect(Money::format($minor, $locale))->toBe($expected);
})->with([
    [1250000, 'ar', '12,500.00 ر.س'],
    [1250000, 'en', 'SAR 12,500.00'],
    [5, 'en', 'SAR 0.05'],
    [0, 'ar', '0.00 ر.س'],
    [-123456, 'en', 'SAR -1,234.56'],
    [100000000000000, 'en', 'SAR 1,000,000,000,000.00'],
]);

it('is a value object with SAR amounts', function () {
    $total = Money::of(1250000)->plus(Money::of(100))->minus(Money::of(50));

    expect($total->amountMinor)->toBe(1250050)
        ->and($total->toArray())->toBe(['amount_minor' => 1250050, 'currency' => 'SAR'])
        ->and(json_encode($total))->toBe('{"amount_minor":1250050,"currency":"SAR"}')
        ->and($total->vatAmount()->amountMinor)->toBe(187508)
        ->and($total->formatted('ar'))->toBe('12,500.50 ر.س')
        ->and(Money::zero()->isZero())->toBeTrue()
        ->and(Money::of(-1)->isNegative())->toBeTrue()
        ->and(Money::of(5)->equals(Money::of(5)))->toBeTrue();
});

it('casts minor amounts to integers and refuses floats', function () {
    $cast = new MinorAmount;
    $model = new class extends Model {};

    expect($cast->get($model, 'amount_minor', '12500', []))->toBe(12500)
        ->and($cast->set($model, 'amount_minor', 12500, []))->toBe(12500)
        ->and($cast->set($model, 'amount_minor', null, []))->toBeNull();

    $cast->set($model, 'amount_minor', 125.5, []);
})->throws(InvalidArgumentException::class);

it('casts minor amounts to Money', function () {
    $cast = new AsMoney;
    $model = new class extends Model {};

    expect($cast->get($model, 'price_minor', 900, []))->toEqual(Money::of(900))
        ->and($cast->set($model, 'price_minor', Money::of(700), []))->toBe(700)
        ->and($cast->set($model, 'price_minor', 5, []))->toBe(5)
        ->and($cast->get($model, 'price_minor', null, []))->toBeNull();
});
