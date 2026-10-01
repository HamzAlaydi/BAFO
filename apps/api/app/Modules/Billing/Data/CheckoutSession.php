<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

/**
 * What a gateway returns when a checkout is created (ARCHITECTURE §13.6): its reference for
 * the payment and the hosted page the browser goes to.
 */
final readonly class CheckoutSession
{
    public function __construct(
        public string $reference,
        public string $redirectUrl,
    ) {}
}
