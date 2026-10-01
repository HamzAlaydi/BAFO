<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use App\Modules\Billing\Enums\EInvoiceStatus;

/**
 * The e-invoicing provider's answer (ARCHITECTURE §13.7):
 * `{status: cleared|reported|rejected, documentId?, zatcaUuid?, qrPayload?, error?}`.
 */
final readonly class EInvoiceResult
{
    public function __construct(
        public EInvoiceStatus $status,
        public ?string $documentId = null,
        public ?string $zatcaUuid = null,
        public ?string $qrPayload = null,
        public ?string $error = null,
    ) {}

    public function isAccepted(): bool
    {
        return in_array($this->status, [EInvoiceStatus::Cleared, EInvoiceStatus::Reported], true);
    }
}
