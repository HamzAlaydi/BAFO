<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Enums;

/**
 * Derived phase of a `live` competition (ARCHITECTURE D8, §6.1 `Competition::phaseAt()`). Not stored.
 */
enum Phase: string
{
    case Sealed = 'sealed';
    case Initial = 'initial';
    case Open = 'open';
    case FinalWindow = 'final_window';

    /** Label in the given locale (default: the app locale). Key: `competitions.enums.phase.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('competitions.enums.phase.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
