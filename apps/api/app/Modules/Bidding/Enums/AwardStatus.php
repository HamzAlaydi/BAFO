<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Enums;

/**
 * `awards.status` (ARCHITECTURE §5.6, §6.6).
 */
enum AwardStatus: string
{
    case Issued = 'issued';
    case Revoked = 'revoked';

    /** Label in the given locale (default: the app locale). Key: `bidding.enums.award_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('bidding.enums.award_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
