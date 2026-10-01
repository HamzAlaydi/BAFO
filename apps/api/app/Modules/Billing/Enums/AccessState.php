<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * An invitee organization's access to a competition (ARCHITECTURE §8.4, API.md §2.6 `access.state`).
 */
enum AccessState: string
{
    case JoinRequired = 'join_required';
    case PlanRequired = 'plan_required';
    case Full = 'full';
    case ReadOnly = 'read_only';
    case Unavailable = 'unavailable';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.access_state.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.access_state.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
