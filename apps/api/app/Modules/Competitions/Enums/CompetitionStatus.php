<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Enums;

/**
 * `competitions.status` (ARCHITECTURE D8, §6.1). Only `CompetitionStateMachine::transition()` writes it.
 */
enum CompetitionStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Closed = 'closed';
    case BafoRound = 'bafo_round';
    case Awarded = 'awarded';
    case NotAwarded = 'not_awarded';
    case Cancelled = 'cancelled';

    /** Label in the given locale (default: the app locale). Key: `competitions.enums.competition_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('competitions.enums.competition_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
