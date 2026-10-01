<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use App\Modules\Billing\Enums\GatewayPaymentStatus;

/**
 * A payment as the gateway sees it (ARCHITECTURE §13.6):
 * `{status: paid|failed|pending, amountMinor, currency, failureCode?, failureMessage?}`.
 */
final readonly class GatewayPaymentState
{
    public function __construct(
        public GatewayPaymentStatus $status,
        public ?int $amountMinor = null,
        public ?string $currency = null,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
    ) {}

    public static function paid(int $amountMinor, string $currency): self
    {
        return new self(GatewayPaymentStatus::Paid, $amountMinor, $currency);
    }

    public static function failed(string $failureCode, ?string $failureMessage = null): self
    {
        return new self(GatewayPaymentStatus::Failed, failureCode: $failureCode, failureMessage: $failureMessage);
    }

    public static function pending(): self
    {
        return new self(GatewayPaymentStatus::Pending);
    }
}
