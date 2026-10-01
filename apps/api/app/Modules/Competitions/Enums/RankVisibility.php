<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Enums;

/**
 * `competitions.rank_visibility` (ARCHITECTURE §5.5, §7.9).
 */
enum RankVisibility: string
{
    case Full = 'full';
    case LeadingFlag = 'leading_flag';
    case None = 'none';

    /** Label in the given locale (default: the app locale). Key: `competitions.enums.rank_visibility.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('competitions.enums.rank_visibility.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
