<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Enums;

/**
 * `offers.stage` (ARCHITECTURE §5.6, §7.3).
 */
enum OfferStage: string
{
    case Initial = 'initial';
    case Live = 'live';
    case Sealed = 'sealed';
    case Bafo = 'bafo';

    /** Label in the given locale (default: the app locale). Key: `bidding.enums.offer_stage.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('bidding.enums.offer_stage.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
