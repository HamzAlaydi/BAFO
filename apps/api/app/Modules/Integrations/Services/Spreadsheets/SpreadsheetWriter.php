<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Spreadsheets;

use App\Modules\Integrations\Enums\ExportFormat;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

/**
 * Writes CSV (UTF-8 with BOM, `,`) or XLSX bytes (ARCHITECTURE §14.7–§14.8). Every string cell
 * goes through the formula-injection guard: a value starting with `=`, `+`, `-`, `@`, a tab or a
 * carriage return is prefixed with `'`.
 */
final class SpreadsheetWriter
{
    /**
     * @param  list<SheetData>  $sheets  the first sheet is the data sheet (CSV writes only that one)
     * @param  (callable(string): void)|null  $afterWrite  receives the XLSX path before it is read back
     */
    public function write(ExportFormat $format, array $sheets, ?callable $afterWrite = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bafo-sheet-');

        if ($path === false) {
            throw new RuntimeException('Cannot create a temporary file.');
        }

        try {
            $format === ExportFormat::Xlsx ? $this->writeXlsx($path, $sheets) : $this->writeCsv($path, $sheets[0]);

            if ($afterWrite !== null) {
                $afterWrite($path);
            }

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    public static function guard(mixed $value): mixed
    {
        // Tab and carriage return too: spreadsheet apps strip them before evaluating (SECURITY_REVIEW S-07).
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    private function writeCsv(string $path, SheetData $sheet): void
    {
        $writer = new CsvWriter;
        $writer->openToFile($path);

        foreach ($sheet->rows() as $row) {
            $writer->addRow(self::row($row));
        }

        $writer->close();
    }

    /**
     * @param  list<SheetData>  $sheets
     */
    private function writeXlsx(string $path, array $sheets): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $bold = (new Style)->setFontBold();

        foreach ($sheets as $index => $sheet) {
            $current = $index === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $current->setName($sheet->name);
            $first = true;

            foreach ($sheet->rows() as $row) {
                $writer->addRow(self::row($row, $first ? $bold : null));
                $first = false;
            }
        }

        $writer->close();
    }

    /**
     * @param  list<string|int|float|bool|null>  $values
     */
    private static function row(array $values, ?Style $style = null): Row
    {
        /** @var list<string|int|float|bool|null> $guarded */
        $guarded = array_map(self::guard(...), $values);

        return Row::fromValues($guarded, $style);
    }
}
