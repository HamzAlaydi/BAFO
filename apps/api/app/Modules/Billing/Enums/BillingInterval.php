<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

use Carbon\CarbonImmutable;

/**
 * `subscriptions.interval` (ARCHITECTURE §5.7). Paid subscriptions only.
 */
enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Annual = 'annual';

    /**
     * Months per period: the divisor of the monthly equivalent (ARCHITECTURE §13.2).
     */
    public function months(): int
    {
        return $this === self::Annual ? 12 : 1;
    }

    /**
     * The end of a period that starts at $start (ARCHITECTURE §13.2: no overflow).
     */
    public function periodEnd(CarbonImmutable $start): CarbonImmutable
    {
        return $this === self::Annual ? $start->addYearNoOverflow() : $start->addMonthNoOverflow();
    }

    /** Label in the given locale (default: the app locale). Key: `billing.enums.billing_interval.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.billing_interval.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
