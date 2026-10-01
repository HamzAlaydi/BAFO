<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * `sponsored_passes.source` (ARCHITECTURE §5.7, §6.3).
 */
enum PassSource: string
{
    case Purchase = 'purchase';
    case FreedSlot = 'freed_slot';
    case AdminGrant = 'admin_grant';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.pass_source.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.pass_source.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
