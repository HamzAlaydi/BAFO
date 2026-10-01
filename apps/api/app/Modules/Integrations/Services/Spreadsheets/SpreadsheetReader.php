<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Spreadsheets;

use DateTimeInterface;
use Generator;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\CSV\Sheet as CsvSheet;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Reader\XLSX\Sheet as XlsxSheet;

/**
 * Reads CSV (UTF-8, with or without BOM, `,` or `;` auto-detected) and XLSX files as rows of
 * strings (ARCHITECTURE §14.7). XLSX reads the sheet whose first row holds the expected header
 * (the template's first sheet), skipping "Read me" and list sheets.
 */
final class SpreadsheetReader
{
    /**
     * @param  list<string>  $headerHint  column names that identify the data sheet of an XLSX file
     * @return Generator<int, list<string>> spreadsheet row number (1 = header) => cells
     */
    public function rows(string $path, string $extension, array $headerHint = []): Generator
    {
        $extension = strtolower($extension);

        if ($extension === 'xlsx') {
            yield from $this->xlsxRows($path, $headerHint);

            return;
        }

        $options = new CsvOptions;
        $options->FIELD_DELIMITER = self::detectDelimiter($path);
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        $reader = new CsvReader($options);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                yield from self::sheetRows($sheet);

                break;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * `;` when the first line has more semicolons than commas (Excel in Arabic and European locales).
     */
    public static function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'rb');
        $line = $handle === false ? false : fgets($handle);

        if ($handle !== false) {
            fclose($handle);
        }

        $line = is_string($line) ? $line : '';

        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    /**
     * @param  list<string>  $headerHint
     * @return Generator<int, list<string>>
     */
    private function xlsxRows(string $path, array $headerHint): Generator
    {
        $options = new XlsxOptions;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        $reader = new XlsxReader($options);
        $reader->open($path);

        try {
            $fallback = null;

            foreach ($reader->getSheetIterator() as $sheet) {
                $fallback ??= $sheet;

                foreach ($sheet->getRowIterator() as $row) {
                    $header = array_map(static fn (string $cell): string => strtolower(trim($cell)), self::cells($row));

                    if ($headerHint === [] || array_diff($headerHint, $header) === []) {
                        yield from self::sheetRows($sheet);

                        return;
                    }

                    break;
                }
            }

            if ($fallback !== null) {
                yield from self::sheetRows($fallback);
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * @return Generator<int, list<string>>
     */
    private static function sheetRows(CsvSheet|XlsxSheet $sheet): Generator
    {
        $number = 0;

        foreach ($sheet->getRowIterator() as $row) {
            $number++;

            yield $number => self::cells($row);
        }
    }

    /**
     * @return list<string>
     */
    private static function cells(Row $row): array
    {
        return array_values(array_map(static function (Cell $cell): string {
            $value = $cell->getValue();

            return match (true) {
                $value === null => '',
                is_bool($value) => $value ? 'true' : 'false',
                // Excel stores CR and VAT numbers typed as numbers: keep every digit.
                is_float($value) && floor($value) === $value => sprintf('%.0f', $value),
                is_int($value), is_float($value) => (string) $value,
                $value instanceof DateTimeInterface => $value->format('Y-m-d'),
                is_string($value) => trim($value),
                default => '',
            };
        }, $row->getCells()));
    }
}
