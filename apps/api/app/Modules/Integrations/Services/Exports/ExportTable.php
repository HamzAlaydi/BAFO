<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Exports;

/**
 * An export ready to write: the file name stem, the header and the data rows.
 */
final readonly class ExportTable
{
    /**
     * @param  list<string>  $header
     * @param  list<list<string|int|float|bool|null>>  $rows
     */
    public function __construct(
        public string $name,
        public array $header,
        public array $rows,
    ) {}

    /**
     * @return list<list<string|int|float|bool|null>>
     */
    public function lines(): array
    {
        return [$this->header, ...$this->rows];
    }
}
