<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * Who covers an invitee's participation fees (ARCHITECTURE §8.4, API.md §2.6 `access.coverage`).
 */
enum Coverage: string
{
    case Sponsored = 'sponsored';
    case OwnPlan = 'own_plan';
    case None = 'none';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.coverage.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.coverage.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
