<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Support\Money\Money;
use App\Support\Pdf\PdfRenderer;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Invoice numbers and the tax-invoice PDF (ARCHITECTURE §5.7, §13.7):
 *
 * - numbers come from `invoice_number_seq`: `BAFO-INV-{YYYY Riyadh}-{nextval padded to 6}`;
 * - the PDF is `billing::pdf.invoice` rendered through PdfRenderer, Arabic and English side by
 *   side, with the ZATCA QR image drawn from `qr_payload`.
 */
final readonly class InvoiceDocument
{
    public const string TIMEZONE = 'Asia/Riyadh';

    public function __construct(private PdfRenderer $pdf) {}

    public function nextNumber(int $year): string
    {
        $row = DB::selectOne("select nextval('invoice_number_seq') as n");
        $next = is_object($row) && isset($row->n) && is_numeric($row->n) ? (int) $row->n : 0;

        return sprintf('BAFO-INV-%d-%06d', $year, $next);
    }

    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing('lines');

        return $this->pdf->render('billing::pdf.invoice', [
            'invoice' => $invoice,
            'seller' => $invoice->seller_snapshot,
            'buyer' => $invoice->buyer_snapshot,
            'lines' => $invoice->lines->map(static fn (InvoiceLine $line): array => [
                'description_ar' => $line->description['ar'] ?? '',
                'description_en' => $line->description['en'] ?? '',
                'quantity' => $line->quantity,
                'unit_price' => self::amount($line->unit_price_minor),
                'net' => self::amount($line->net_minor),
            ])->all(),
            'amounts' => [
                'subtotal' => self::amount($invoice->subtotal_minor),
                'discount' => self::amount($invoice->discount_minor),
                'taxable' => self::amount($invoice->subtotal_minor - $invoice->discount_minor),
                'vat' => self::amount($invoice->vat_minor),
                'total' => self::amount($invoice->total_minor),
            ],
            'vat_percent' => rtrim(rtrim(number_format($invoice->vat_rate_bp / 100, 2, '.', ''), '0'), '.'),
            'issued_at' => $invoice->issued_at->setTimezone(self::TIMEZONE)->format('Y-m-d H:i'),
            'qr_image' => $this->qrImage($invoice->qr_payload),
        ], 'ar');
    }

    /**
     * Both display formats of CONVENTIONS §9.2 (the PDF shows Arabic and English side by side).
     *
     * @return array{ar: string, en: string}
     */
    private static function amount(int $minor): array
    {
        return ['ar' => Money::format($minor, 'ar'), 'en' => Money::format($minor, 'en')];
    }

    /**
     * A data URI of the QR image, or null when there is no payload or no QR library.
     */
    private function qrImage(?string $payload): ?string
    {
        if ($payload === null || $payload === '' || ! class_exists(QRCode::class)) {
            return null;
        }

        try {
            $options = new QROptions([
                'outputType' => extension_loaded('gd') ? QROutputInterface::GDIMAGE_PNG : QROutputInterface::MARKUP_SVG,
                'outputBase64' => true,
                'scale' => 4,
            ]);

            $image = (new QRCode($options))->render($payload);

            return is_string($image) ? $image : null;
        } catch (Throwable) {
            return null;
        }
    }
}
