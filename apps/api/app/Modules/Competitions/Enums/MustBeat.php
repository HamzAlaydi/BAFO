<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Enums;

/**
 * `competitions.must_beat` (ARCHITECTURE §5.5, §7.2). Null for sealed competitions.
 */
enum MustBeat: string
{
    case Own = 'own';
    case Best = 'best';

    /** Label in the given locale (default: the app locale). Key: `competitions.enums.must_beat.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('competitions.enums.must_beat.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
