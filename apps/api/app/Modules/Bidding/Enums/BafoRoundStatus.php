<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Enums;

/**
 * `bafo_rounds.status` (ARCHITECTURE §5.6, §6.6).
 */
enum BafoRoundStatus: string
{
    case Running = 'running';
    case Ended = 'ended';

    /** Label in the given locale (default: the app locale). Key: `bidding.enums.bafo_round_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('bidding.enums.bafo_round_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
