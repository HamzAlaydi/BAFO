<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Events;

use App\Modules\Integrations\Models\ImportJob;

/**
 * A vendor import reached `completed` or `failed` (ARCHITECTURE §10). Notifications sends
 * `import.finished` to its creator.
 */
final readonly class ImportFinished
{
    public function __construct(
        public ImportJob $importJob,
    ) {}
}
