<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services\EInvoicing;

use App\Modules\Billing\Contracts\EInvoicing;
use App\Modules\Billing\Data\EInvoiceResult;
use App\Modules\Billing\Enums\EInvoiceStatus;
use App\Modules\Billing\Models\Invoice;
use Illuminate\Support\Str;

/**
 * The `fake` e-invoicing provider (ARCHITECTURE §13.7, D18): every invoice is `cleared`, with
 * ZATCA-shaped identifiers and a real TLV QR payload built from the invoice's own snapshot.
 */
final class FakeEInvoicing implements EInvoicing
{
    public const string NAME = 'fake';

    public function issue(Invoice $invoice): EInvoiceResult
    {
        $seller = $invoice->seller_snapshot;

        return new EInvoiceResult(
            status: EInvoiceStatus::Cleared,
            documentId: 'fake-'.$invoice->public_id,
            zatcaUuid: (string) Str::uuid(),
            qrPayload: ZatcaQr::encode([
                1 => is_string($seller['name_ar'] ?? null) ? $seller['name_ar'] : '',
                2 => is_string($seller['vat_number'] ?? null) ? $seller['vat_number'] : '',
                3 => $invoice->issued_at->utc()->format('Y-m-d\TH:i:s\Z'),
                4 => ZatcaQr::amount($invoice->total_minor),
                5 => ZatcaQr::amount($invoice->vat_minor),
            ]),
        );
    }
}
