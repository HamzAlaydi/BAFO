<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Enums;

/**
 * `competitions.source` (ARCHITECTURE §5.5).
 */
enum CompetitionSource: string
{
    case Web = 'web';
    case Ios = 'ios';
    case Android = 'android';
    case Api = 'api';

    /** Label in the given locale (default: the app locale). Key: `competitions.enums.competition_source.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('competitions.enums.competition_source.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
