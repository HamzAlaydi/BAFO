<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

/**
 * `import_jobs.mode` (ARCHITECTURE §5.4, §14.7).
 */
enum ImportMode: string
{
    case Validate = 'validate';
    case Commit = 'commit';

    /** Label in the given locale (default: the app locale). Key: `integrations.enums.import_mode.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('integrations.enums.import_mode.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
