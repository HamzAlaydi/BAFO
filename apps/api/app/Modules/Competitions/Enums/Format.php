<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Enums;

/**
 * `competitions.format` and `competition_presets.format` (ARCHITECTURE §5.5, D7).
 *
 * CONTRACT-GAP: owned by Competitions, read by Catalog presets (as `Direction`).
 */
enum Format: string
{
    case Live = 'live';
    case Sealed = 'sealed';

    /** Label in the given locale (default: the app locale). Key: `competitions.enums.format.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('competitions.enums.format.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
