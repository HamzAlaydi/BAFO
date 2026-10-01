<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\PurchaseKind;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;

/**
 * A priced subscription purchase before discounts (ARCHITECTURE §13.2): the kind, the plan
 * line (`unit × quantity`), the pro-rata upgrade credit and the subscription it replaces.
 */
final readonly class SubscriptionPurchase
{
    public function __construct(
        public PurchaseKind $kind,
        public Plan $plan,
        public BillingInterval $interval,
        public int $seats,
        public int $unitPriceMinor,
        public int $quantity,
        public int $creditMinor,
        public ?Subscription $replaces,
    ) {}

    public function subtotalMinor(): int
    {
        return $this->unitPriceMinor * $this->quantity;
    }
}
