<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * `competition_sponsorships.status` (ARCHITECTURE §5.7).
 */
enum SponsorshipStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Settled = 'settled';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.sponsorship_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.sponsorship_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
