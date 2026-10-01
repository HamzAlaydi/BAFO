<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\EInvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentLine;
use App\Modules\Billing\Services\EInvoicing\FakeEInvoicing;
use App\Modules\Billing\Services\InvoiceDocument;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * Creates the tax invoice of a succeeded payment (ARCHITECTURE §13.7 step 1), once per payment,
 * then clears it with the e-invoicing provider (IssueEInvoice). Skipped when the total is 0.
 *
 * - `number` from `invoice_number_seq` with the Riyadh year; `issue_date = supply_date` = the
 *   Riyadh date of `paid_at`;
 * - the lines are copied from `payment_lines`; `discount_minor` = discount + credit;
 * - `seller_snapshot` from `bafo.billing.seller`, `buyer_snapshot` from the organization.
 */
final readonly class InvoicePayment
{
    public function __construct(
        private InvoiceDocument $document,
        private IssueEInvoice $issueEInvoice,
    ) {}

    public function handle(Payment $payment, Actor $actor): ?Invoice
    {
        $invoice = DB::transaction(function () use ($payment, $actor): ?Invoice {
            /** @var Payment $locked */
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status !== PaymentStatus::Succeeded || $locked->total_minor === 0) {
                return null;
            }

            $existing = Invoice::query()->where('payment_id', $locked->id)->first();

            if ($existing !== null) {
                return $existing;
            }

            $paidAt = ($locked->paid_at ?? $locked->updated_at ?? now()->toImmutable())->setTimezone(InvoiceDocument::TIMEZONE);
            $organization = $locked->organization;

            $invoice = Invoice::query()->create([
                'number' => $this->document->nextNumber((int) $paidAt->format('Y')),
                'organization_id' => $locked->organization_id,
                'payment_id' => $locked->id,
                'type' => InvoiceType::TaxInvoice,
                'issue_date' => $paidAt->toDateString(),
                'supply_date' => $paidAt->toDateString(),
                'currency' => $locked->currency,
                'subtotal_minor' => $locked->subtotal_minor,
                'discount_minor' => $locked->discount_minor + $locked->credit_minor,
                'vat_minor' => $locked->vat_minor,
                'total_minor' => $locked->total_minor,
                'vat_rate_bp' => $locked->vat_rate_bp,
                'seller_snapshot' => self::sellerSnapshot(),
                'buyer_snapshot' => self::buyerSnapshot($organization),
                'einvoice_provider' => self::provider(),
                'einvoice_status' => EInvoiceStatus::Pending,
                'issued_at' => $locked->paid_at ?? now(),
            ]);

            $locked->lines->each(static function (PaymentLine $line) use ($invoice): void {
                $invoice->lines()->create([
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price_minor' => $line->unit_price_minor,
                    'net_minor' => $line->net_minor,
                ]);
            });

            AuditLogger::log('invoice.created', $invoice, meta: [
                'number' => $invoice->number,
                'payment_id' => $locked->public_id,
            ], actor: $actor, organizationId: $locked->organization_id);

            return $invoice;
        });

        if ($invoice === null || ! in_array($invoice->einvoice_status, [EInvoiceStatus::Pending, EInvoiceStatus::Failed], true)) {
            return $invoice;
        }

        return $this->issueEInvoice->handle($invoice, $actor);
    }

    /**
     * @return array<string, string>
     */
    public static function sellerSnapshot(): array
    {
        $seller = (array) config('bafo.billing.seller', []);

        return array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', [
            'name_ar' => $seller['name_ar'] ?? '',
            'name_en' => $seller['name_en'] ?? '',
            'vat_number' => $seller['vat_number'] ?? '',
            'cr_number' => $seller['cr_number'] ?? '',
            'address' => $seller['address'] ?? '',
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    public static function buyerSnapshot(Organization $organization): array
    {
        return [
            'name' => $organization->name,
            'legal_name_ar' => $organization->legal_name_ar,
            'legal_name_en' => $organization->legal_name_en,
            'cr_number' => $organization->cr_number,
            'vat_number' => $organization->vat_registered ? $organization->vat_number : null,
            'city' => $organization->city,
            'building_number' => $organization->address_building_number,
            'street' => $organization->address_street,
            'district' => $organization->address_district,
            'postal_code' => $organization->address_postal_code,
            'additional_number' => $organization->address_additional_number,
        ];
    }

    private static function provider(): string
    {
        $driver = config('bafo.billing.einvoicing.driver', FakeEInvoicing::NAME);

        return is_string($driver) ? $driver : FakeEInvoicing::NAME;
    }
}
