<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use App\Modules\Billing\Enums\BillingInterval;

/**
 * The validated body of `POST /billing/checkout/subscription` (API.md §1.7), plus the optional
 * `Idempotency-Key`.
 */
final readonly class SubscriptionCheckoutInput
{
    public function __construct(
        public string $planId,
        public BillingInterval $interval,
        public ?int $seats,
        public ?string $couponCode,
        public string $returnUrl,
        public ?string $idempotencyKey = null,
    ) {}
}
