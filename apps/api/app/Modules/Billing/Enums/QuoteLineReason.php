<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * Why a sponsorship quote line needs no pass (API.md §2.10 `SponsorshipQuote.lines[].reason`).
 */
enum QuoteLineReason: string
{
    case NotSelected = 'not_selected';
    case CapReached = 'cap_reached';
    case OwnPlan = 'own_plan';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.quote_line_reason.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.quote_line_reason.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
