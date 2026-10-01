<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Fields;
use App\Modules\Admin\Filament\Support\SettingsForm;
use App\Modules\Competitions\Enums\CompetitionStatus;
use Tests\TestCase;

uses(TestCase::class);

/*
 * The panel's money input (SAR ↔ halalas, never a float), the settings value casts and the
 * display helpers.
 */

it('converts typed SAR amounts to halalas without floats', function (mixed $input, ?int $expected) {
    expect(Fields::toMinor($input))->toBe($expected);
})->with([
    ['315', 31_500],
    ['315.5', 31_550],
    ['315.05', 31_505],
    ['0.01', 1],
    [' 12500.00 ', 1_250_000],
    ['12.345', null],
    ['-5', null],
    ['abc', null],
    [null, null],
    [7, 700],
]);

it('formats halalas for the money input', function () {
    expect(Fields::toDecimal(31_505))->toBe('315.05')
        ->and(Fields::toDecimal(5))->toBe('0.05')
        ->and(Fields::toDecimal(1_250_000))->toBe('12500.00');
});

it('casts submitted settings to the shape of their default', function (mixed $value, mixed $default, mixed $expected) {
    expect(SettingsForm::cast($value, $default))->toBe($expected);
})->with([
    'bool' => ['1', false, true],
    'int' => ['150', 200, 150],
    'int keeps the default on junk' => ['x', 200, 200],
    'string' => ['  1.2.0 ', '1.0.0', '1.2.0'],
    'list of ints' => [['15', '5', ''], [10, 2], [15, 5]],
    'object' => [['min' => '20', 'max' => '500', 'extra' => 'x'], ['min' => 30, 'max' => 600], ['min' => 20, 'max' => 500]],
    'object of strings' => [['ar' => 'صيانة', 'en' => null], ['ar' => '', 'en' => ''], ['ar' => 'صيانة', 'en' => '']],
]);

it('names setting fields without dots', function () {
    expect(SettingsForm::field('app.maintenance.enabled'))->toBe('app__maintenance__enabled');
});

it('labels enums and picks status colours', function () {
    app()->setLocale('en');

    expect(Display::enum(CompetitionStatus::Live))->toBe(CompetitionStatus::Live->label('en'))
        ->and(Display::color(CompetitionStatus::Live))->toBe('success')
        ->and(Display::color('failed'))->toBe('danger')
        ->and(Display::color('superseded'))->toBe('gray')
        ->and(Display::translated(['ar' => 'الرياض', 'en' => 'Riyadh']))->toBe('Riyadh')
        ->and(Display::translated(['ar' => 'الرياض']))->toBe('الرياض')
        ->and(Display::money(1_250_050))->toBe('SAR 12,500.50');
});
