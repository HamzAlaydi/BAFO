<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

/**
 * A verified gateway webhook (ARCHITECTURE §13.6): the gateway reference of the payment it is
 * about. The state itself is always re-read with `PaymentGateway::fetch()`.
 */
final readonly class GatewayWebhook
{
    public function __construct(public string $reference) {}
}
