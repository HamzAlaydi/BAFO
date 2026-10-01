<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use App\Modules\Billing\Enums\SponsorshipMode;
use App\Modules\Competitions\Models\Invitation;
use App\Support\Money\Money;

/**
 * The price of covering participation fees (ARCHITECTURE §13.5 "Quote", API.md §2.10
 * `SponsorshipQuote`). `$needs` holds, in order, the invitations that need a pass: the first
 * `passesToReserve` of them take free slots and the rest must be bought.
 */
final readonly class SponsorshipQuote
{
    /**
     * @param  list<SponsorshipQuoteLine>  $lines
     * @param  list<Invitation>  $needs
     */
    public function __construct(
        public ?SponsorshipMode $mode,
        public ?int $maxPasses,
        public int $unitPriceMinor,
        public int $fundedPasses,
        public int $freeSlots,
        public array $lines,
        public array $needs,
        public int $passesToReserve,
        public int $passesToBuy,
        public PriceBreakdown $price,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode->value ?? 'none',
            'max_passes' => $this->maxPasses,
            'unit_price_minor' => $this->unitPriceMinor,
            'vat_rate_bp' => $this->price->vatRateBp,
            'currency' => Money::CURRENCY,
            'funded_passes' => $this->fundedPasses,
            'free_slots' => $this->freeSlots,
            'lines' => array_map(static fn (SponsorshipQuoteLine $line): array => $line->toArray(), $this->lines),
            'passes_to_reserve' => $this->passesToReserve,
            'passes_to_buy' => $this->passesToBuy,
            'subtotal_minor' => $this->price->subtotalMinor,
            'discount_minor' => $this->price->discountMinor,
            'vat_minor' => $this->price->vatMinor,
            'total_minor' => $this->price->totalMinor,
        ];
    }
}
