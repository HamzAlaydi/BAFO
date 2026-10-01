<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * `sponsored_passes.release_reason` (ARCHITECTURE §5.7, §6.3).
 */
enum PassReleaseReason: string
{
    case Declined = 'declined';
    case Revoked = 'revoked';
    case CoveredByOwnPlan = 'covered_by_own_plan';
    case DuplicateOrganization = 'duplicate_organization';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.pass_release_reason.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.pass_release_reason.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
