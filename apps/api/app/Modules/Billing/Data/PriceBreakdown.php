<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

/**
 * The amounts of a checkout in integer halalas (ARCHITECTURE §13.2):
 *
 *     taxable = subtotal − credit − discount
 *     vat     = Money::vat(taxable, rate)
 *     total   = taxable + vat
 */
final readonly class PriceBreakdown
{
    public function __construct(
        public int $subtotalMinor,
        public int $creditMinor,
        public int $discountMinor,
        public int $vatRateBp,
        public int $vatMinor,
        public int $totalMinor,
    ) {}

    public function taxableMinor(): int
    {
        return $this->subtotalMinor - $this->creditMinor - $this->discountMinor;
    }
}
