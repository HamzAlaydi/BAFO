<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Enums\EInvoiceStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API.md §2.10 `Invoice`. The PDF is available once the e-invoice is cleared (or reported) and
 * rendered; it downloads from `/api/app/v1/billing/invoices/{id}/pdf`.
 *
 * @mixin Invoice
 */
final class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['lines', 'payment']);

        return [
            'id' => $this->public_id,
            'number' => $this->number,
            'type' => $this->type->value,
            'issue_date' => Iso::date($this->issue_date),
            'currency' => $this->currency,
            'subtotal_minor' => $this->subtotal_minor,
            'discount_minor' => $this->discount_minor,
            'vat_rate_bp' => $this->vat_rate_bp,
            'vat_minor' => $this->vat_minor,
            'total_minor' => $this->total_minor,
            'einvoice_status' => $this->einvoice_status->value,
            'zatca_uuid' => $this->zatca_uuid,
            'lines' => $this->lines->map(static fn (InvoiceLine $line): array => [
                'description' => $line->translated('description'),
                'quantity' => $line->quantity,
                'unit_price_minor' => $line->unit_price_minor,
                'net_minor' => $line->net_minor,
            ])->values()->all(),
            'pdf' => [
                'available' => self::pdfAvailable($this->resource),
                'download_path' => '/api/app/v1/billing/invoices/'.$this->public_id.'/pdf',
            ],
            'payment_id' => $this->payment?->public_id,
            'issued_at' => Iso::format($this->issued_at),
        ];
    }

    public static function pdfAvailable(Invoice $invoice): bool
    {
        return $invoice->pdf_file_id !== null
            && in_array($invoice->einvoice_status, [EInvoiceStatus::Cleared, EInvoiceStatus::Reported], true);
    }
}
