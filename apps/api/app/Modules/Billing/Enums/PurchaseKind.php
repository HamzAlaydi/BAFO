<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * The kind of a subscription purchase (ARCHITECTURE §13.2).
 */
enum PurchaseKind: string
{
    case New = 'new';
    case Renewal = 'renewal';
    case Upgrade = 'upgrade';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.purchase_kind.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.purchase_kind.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
