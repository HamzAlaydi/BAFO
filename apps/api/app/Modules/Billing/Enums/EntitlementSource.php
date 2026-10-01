<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * `participants.entitlement_source` (ARCHITECTURE §5.5); returned by `AccessPolicy::resolveJoin()` (§3.6).
 *
 * CONTRACT-GAP: the contract does not name the owning module; it lives with the entitlement policy (Billing).
 */
enum EntitlementSource: string
{
    case Plan = 'plan';
    case SponsoredPass = 'sponsored_pass';
    case Grant = 'grant';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.entitlement_source.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.entitlement_source.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
