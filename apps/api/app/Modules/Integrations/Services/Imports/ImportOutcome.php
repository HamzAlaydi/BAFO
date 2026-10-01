<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Imports;

use App\Support\Files\File;

/**
 * The counters and error outputs of a processed import (ARCHITECTURE §5.4 `import_jobs`).
 */
final readonly class ImportOutcome
{
    /**
     * @param  list<array{row: int, column: string, code: string, message: string}>  $errorsPreview
     */
    public function __construct(
        public int $totalRows,
        public int $validRows,
        public int $createdRows,
        public int $updatedRows,
        public int $errorRows,
        public array $errorsPreview,
        public ?File $errorsFile,
    ) {}
}
