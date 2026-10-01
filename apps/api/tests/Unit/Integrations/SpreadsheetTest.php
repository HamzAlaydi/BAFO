<?php

declare(strict_types=1);

use App\Modules\Integrations\Enums\ExportFormat;
use App\Modules\Integrations\Services\Exports\ExportBuilder;
use App\Modules\Integrations\Services\Spreadsheets\SheetData;
use App\Modules\Integrations\Services\Spreadsheets\SpreadsheetReader;
use App\Modules\Integrations\Services\Spreadsheets\SpreadsheetWriter;
use Carbon\CarbonImmutable;

it('neutralises cells that a spreadsheet would run as formulas', function (mixed $value, mixed $expected) {
    expect(SpreadsheetWriter::guard($value))->toBe($expected);
})->with([
    ['=HYPERLINK("http://evil")', '\'=HYPERLINK("http://evil")'],
    ['+966551234567', "'+966551234567"],
    ['-1+2', "'-1+2"],
    ['@SUM(A1)', "'@SUM(A1)"],
    ['شركة', 'شركة'],
    ['', ''],
    [12, 12],
    [null, null],
]);

it('writes CSV with a BOM and reads it back with either delimiter', function () {
    $writer = new SpreadsheetWriter;
    $csv = $writer->write(ExportFormat::Csv, [new SheetData('x', [['name', 'email'], ['شركة الريادة', '=cmd']])]);

    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($csv)->toContain("'=cmd");

    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, "\xEF\xBB\xBFname;email\n\"Al; Co\";a@b.sa\n");

    expect(SpreadsheetReader::detectDelimiter($path))->toBe(';')
        ->and(iterator_to_array((new SpreadsheetReader)->rows($path, 'csv')))->toBe([1 => ['name', 'email'], 2 => ['Al; Co', 'a@b.sa']]);

    unlink($path);
});

it('writes XLSX sheets and reads the data sheet back', function () {
    $bytes = (new SpreadsheetWriter)->write(ExportFormat::Xlsx, [
        new SheetData('Read me', [['about'], ['text']]),
        new SheetData('vendors', [['name', 'email', 'cr_number'], ['Al Riyada', 'a@b.sa', 1010987654]]),
    ]);
    $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    file_put_contents($path, $bytes);

    expect(iterator_to_array((new SpreadsheetReader)->rows($path, 'xlsx', ['name', 'email'])))
        ->toBe([1 => ['name', 'email', 'cr_number'], 2 => ['Al Riyada', 'a@b.sa', '1010987654']]);

    unlink($path);
});

it('formats amounts as decimal SAR and times in Riyadh', function () {
    expect(ExportBuilder::sar(2215000))->toBe('22150.00')
        ->and(ExportBuilder::sar(5))->toBe('0.05')
        ->and(ExportBuilder::sar(null))->toBe('')
        ->and(ExportBuilder::time(CarbonImmutable::parse('2026-10-01T09:00:00.123Z')))->toBe('2026-10-01 12:00:00.123');
});
