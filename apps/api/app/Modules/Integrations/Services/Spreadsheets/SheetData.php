<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Spreadsheets;

/**
 * One sheet to write: a name (XLSX only) and its rows, the header first.
 */
final readonly class SheetData
{
    /**
     * @param  iterable<list<string|int|float|bool|null>>  $rows
     */
    public function __construct(
        public string $name,
        private iterable $rows,
    ) {}

    /**
     * @return iterable<list<string|int|float|bool|null>>
     */
    public function rows(): iterable
    {
        return $this->rows;
    }
}
