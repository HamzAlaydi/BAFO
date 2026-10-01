<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\EInvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Billing\Services\InvoiceDocument;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin "Record credit note" (ARCHITECTURE §13.7, §16): the MVP issues credit notes in the
 * e-invoicing provider's portal; this records one against a tax invoice, with the provider's
 * document reference. Automatic credit notes are out of scope (§18).
 *
 * CONTRACT-GAP: the contract does not say how much a credit note covers; the MVP records a full
 * credit of the original invoice (lines and amounts copied), once per invoice, as `reported`.
 */
final readonly class RecordCreditNote
{
    public function __construct(private InvoiceDocument $document) {}

    public function handle(Invoice $original, string $providerDocumentId, Actor $actor): Invoice
    {
        $reference = trim($providerDocumentId);

        if ($reference === '') {
            $message = __('billing.validation.reference_required');

            throw ValidationException::withMessages(['reference' => [is_string($message) ? $message : 'reference']]);
        }

        return DB::transaction(function () use ($original, $reference, $actor): Invoice {
            /** @var Invoice $locked */
            $locked = Invoice::query()->lockForUpdate()->findOrFail($original->id);

            if ($locked->type !== InvoiceType::TaxInvoice || $locked->creditNotes()->exists()) {
                throw new ApiException('invalid_state_transition', status: 409, details: ['status' => $locked->type->value]);
            }

            $now = CarbonImmutable::now()->setTimezone(InvoiceDocument::TIMEZONE);

            $note = Invoice::query()->create([
                'number' => $this->document->nextNumber((int) $now->format('Y')),
                'organization_id' => $locked->organization_id,
                'payment_id' => null,
                'type' => InvoiceType::CreditNote,
                'original_invoice_id' => $locked->id,
                'issue_date' => $now->toDateString(),
                'supply_date' => $locked->supply_date->toDateString(),
                'currency' => $locked->currency,
                'subtotal_minor' => $locked->subtotal_minor,
                'discount_minor' => $locked->discount_minor,
                'vat_minor' => $locked->vat_minor,
                'total_minor' => $locked->total_minor,
                'vat_rate_bp' => $locked->vat_rate_bp,
                'seller_snapshot' => $locked->seller_snapshot,
                'buyer_snapshot' => $locked->buyer_snapshot,
                'einvoice_provider' => $locked->einvoice_provider,
                'einvoice_status' => EInvoiceStatus::Reported,
                'einvoice_document_id' => mb_substr($reference, 0, 120),
                'issued_at' => $now,
                'cleared_at' => $now,
            ]);

            $locked->lines->each(static function (InvoiceLine $line) use ($note): void {
                $note->lines()->create([
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price_minor' => $line->unit_price_minor,
                    'net_minor' => $line->net_minor,
                ]);
            });

            AuditLogger::log('invoice.credit_note_recorded', $note, meta: [
                'original' => $locked->number,
                'reference' => $reference,
            ], actor: $actor, organizationId: $locked->organization_id);

            return $note;
        });
    }
}
