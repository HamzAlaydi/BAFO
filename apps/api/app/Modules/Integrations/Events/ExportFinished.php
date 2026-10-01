<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Events;

use App\Modules\Integrations\Models\ExportJob;

/**
 * An export reached `completed` or `failed` (ARCHITECTURE §10). Notifications sends
 * `export.finished` to its creator.
 */
final readonly class ExportFinished
{
    public function __construct(
        public ExportJob $exportJob,
    ) {}
}
