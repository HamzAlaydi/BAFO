<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

/**
 * `import_jobs.status` and `export_jobs.status` (ARCHITECTURE §5.4, §6.6).
 */
enum JobStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    /** Label in the given locale (default: the app locale). Key: `integrations.enums.job_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('integrations.enums.job_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
