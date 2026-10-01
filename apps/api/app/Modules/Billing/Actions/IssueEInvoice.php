<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Contracts\EInvoicing;
use App\Modules\Billing\Enums\EInvoiceStatus;
use App\Modules\Billing\Events\InvoiceIssued;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\InvoiceDocument;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Files\FilePurpose;
use App\Support\Files\FileStorage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Clears an invoice with the e-invoicing provider and renders its PDF (ARCHITECTURE §13.7
 * steps 2–4). Used after the invoice is created, by `billing:retry-einvoices` and by the
 * admin "Retry e-invoice".
 *
 * - cleared or reported: the ids and QR are stored, `billing::pdf.invoice` is rendered and
 *   saved (purpose `invoice_pdf`), `cleared_at` is set and InvoiceIssued dispatched;
 * - rejected or an error: `einvoice_status = rejected | failed`, attempts + 1, the error kept.
 */
final readonly class IssueEInvoice
{
    public const int MAX_ATTEMPTS = 10;

    public function __construct(
        private EInvoicing $provider,
        private InvoiceDocument $document,
        private FileStorage $files,
    ) {}

    public function handle(Invoice $invoice, Actor $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor): Invoice {
            /** @var Invoice $locked */
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (in_array($locked->einvoice_status, [EInvoiceStatus::Cleared, EInvoiceStatus::Reported], true)) {
                // Already accepted: only a missing PDF is (re-)rendered.
                if ($locked->pdf_file_id === null) {
                    $this->storePdf($locked);
                }

                return $locked;
            }

            try {
                $result = $this->provider->issue($locked);
            } catch (Throwable $e) {
                Log::warning('E-invoice issue failed.', ['invoice_id' => $locked->public_id, 'error' => $e->getMessage()]);

                return $this->failed($locked, EInvoiceStatus::Failed, $e->getMessage(), $actor);
            }

            if (! $result->isAccepted()) {
                return $this->failed($locked, EInvoiceStatus::Rejected, $result->error, $actor);
            }

            $locked->forceFill([
                'einvoice_status' => $result->status,
                'einvoice_document_id' => $result->documentId,
                'zatca_uuid' => $result->zatcaUuid,
                'qr_payload' => $result->qrPayload,
                'einvoice_attempts' => $locked->einvoice_attempts + 1,
                'einvoice_last_error' => null,
                'cleared_at' => CarbonImmutable::now(),
            ])->save();

            $this->storePdf($locked);

            AuditLogger::log('invoice.issued', $locked, meta: [
                'number' => $locked->number,
                'einvoice_status' => $locked->einvoice_status->value,
            ], actor: $actor, organizationId: $locked->organization_id);

            InvoiceIssued::dispatch($locked);

            return $locked;
        });
    }

    /**
     * Renders and stores the PDF. A rendering error does not undo the e-invoice: the PDF
     * endpoint answers 409 until `billing:retry-einvoices` renders it.
     */
    private function storePdf(Invoice $invoice): void
    {
        try {
            $pdf = $this->files->storeContents(
                $this->document->render($invoice),
                $invoice->number.'.pdf',
                'application/pdf',
                FilePurpose::InvoicePdf,
                $invoice->organization_id,
            );
            $invoice->forceFill(['pdf_file_id' => $pdf->id])->save();
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function failed(Invoice $invoice, EInvoiceStatus $status, ?string $error, Actor $actor): Invoice
    {
        $invoice->forceFill([
            'einvoice_status' => $status,
            'einvoice_attempts' => $invoice->einvoice_attempts + 1,
            'einvoice_last_error' => $error === null ? null : mb_substr($error, 0, 2000),
        ])->save();

        AuditLogger::log('invoice.einvoice_failed', $invoice, meta: [
            'einvoice_status' => $status->value,
            'attempts' => $invoice->einvoice_attempts,
        ], actor: $actor, organizationId: $invoice->organization_id);

        return $invoice;
    }
}
