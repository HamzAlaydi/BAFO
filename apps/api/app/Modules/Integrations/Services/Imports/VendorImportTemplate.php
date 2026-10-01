<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Imports;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Region;
use App\Modules\Integrations\Enums\ExportFormat;
use App\Modules\Integrations\Services\Spreadsheets\SheetData;
use App\Modules\Integrations\Services\Spreadsheets\SpreadsheetWriter;
use App\Modules\Integrations\Services\Spreadsheets\XlsxDataValidation;

/**
 * `GET /integrations/imports/templates/vendors?format=csv|xlsx` (ARCHITECTURE §14.7).
 *
 *   CSV   the header row, UTF-8 with BOM.
 *   XLSX  sheet `vendors` (header), sheet `Read me` (each column in Arabic and English), sheet
 *         `Lists` (region codes, category codes, statuses) feeding dropdowns on `region_code`,
 *         `category_codes` (a suggestion: the cell may hold several codes) and `status`.
 */
final readonly class VendorImportTemplate
{
    private const int MAX_TEMPLATE_ROW = 10001;

    public function __construct(private SpreadsheetWriter $writer) {}

    public function build(ExportFormat $format): string
    {
        $header = new SheetData('vendors', [VendorColumns::ALL]);

        if ($format === ExportFormat::Csv) {
            return $this->writer->write($format, [$header]);
        }

        $regions = Region::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->pluck('code')->all();
        $categories = Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->pluck('code')->all();
        $statuses = ['active', 'blocked'];

        $lists = [['region_code', 'category_codes', 'status']];
        $length = max(count($regions), count($categories), count($statuses));

        for ($i = 0; $i < $length; $i++) {
            $lists[] = [$regions[$i] ?? null, $categories[$i] ?? null, $statuses[$i] ?? null];
        }

        $validations = [];

        foreach ([
            ['region_code', 'A', count($regions), true],
            ['category_codes', 'B', count($categories), false],
            ['status', 'C', count($statuses), true],
        ] as [$column, $listColumn, $count, $strict]) {
            $letter = self::columnLetter((int) array_search($column, VendorColumns::ALL, true));
            $validations[] = [
                'range' => $letter.'2:'.$letter.self::MAX_TEMPLATE_ROW,
                'source' => 'Lists!$'.$listColumn.'$2:$'.$listColumn.'$'.max(2, $count + 1),
                'strict' => $strict,
            ];
        }

        return $this->writer->write(
            $format,
            [$header, new SheetData('Read me', $this->readMe()), new SheetData('Lists', $lists)],
            static fn (string $path) => XlsxDataValidation::addLists($path, $validations),
        );
    }

    /**
     * @return list<list<string>>
     */
    private function readMe(): array
    {
        $rows = [['column', 'required', 'الوصف', 'Description']];

        foreach (VendorColumns::ALL as $column) {
            $rows[] = [
                $column,
                in_array($column, VendorColumns::REQUIRED, true) ? 'yes' : 'no',
                self::trans('integrations.import.template.columns.'.$column, 'ar'),
                self::trans('integrations.import.template.columns.'.$column, 'en'),
            ];
        }

        $rows[] = [];
        $rows[] = ['', '', self::trans('integrations.import.template.notes', 'ar'), self::trans('integrations.import.template.notes', 'en')];

        return $rows;
    }

    private static function columnLetter(int $index): string
    {
        $letter = '';

        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $letter = chr(65 + (($index - 1) % 26)).$letter;
        }

        return $letter;
    }

    private static function trans(string $key, string $locale): string
    {
        $value = __($key, [], $locale);

        return is_string($value) ? $value : $key;
    }
}
