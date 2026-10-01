<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Enums;

/**
 * `competitions.result_publication` (ARCHITECTURE §5.5, §7.2 R4).
 */
enum ResultPublication: string
{
    case None = 'none';
    case OutcomeOnly = 'outcome_only';
    case OutcomeAndAmount = 'outcome_and_amount';

    /** Label in the given locale (default: the app locale). Key: `competitions.enums.result_publication.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('competitions.enums.result_publication.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
