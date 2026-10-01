<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * `competition_sponsorships.mode` (ARCHITECTURE §5.7, §13.5). A row exists only when the mode is not none.
 */
enum SponsorshipMode: string
{
    case All = 'all';
    case Selected = 'selected';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.sponsorship_mode.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.sponsorship_mode.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
