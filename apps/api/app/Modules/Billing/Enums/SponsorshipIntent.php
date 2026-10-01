<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * What a sponsorship checkout completes once paid (ARCHITECTURE §13.5).
 */
enum SponsorshipIntent: string
{
    case Publish = 'publish';
    case Invite = 'invite';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.sponsorship_intent.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.sponsorship_intent.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
