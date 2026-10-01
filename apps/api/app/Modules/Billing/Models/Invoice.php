<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Database\Factories\InvoiceFactory;
use App\Modules\Billing\Enums\EInvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Identity\Models\Organization;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Files\File;
use App\Support\Money\Casts\MinorAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tax invoice or an admin-recorded credit note (ARCHITECTURE §5.7 `invoices`, §13.7). Numbers
 * come from `invoice_number_seq`: `BAFO-INV-{YYYY}-{000001}`.
 *
 * @property int $id
 * @property string $public_id
 * @property string $number
 * @property int $organization_id
 * @property int|null $payment_id
 * @property InvoiceType $type
 * @property int|null $original_invoice_id
 * @property CarbonImmutable $issue_date
 * @property CarbonImmutable $supply_date
 * @property string $currency
 * @property int $subtotal_minor
 * @property int $discount_minor
 * @property int $vat_minor
 * @property int $total_minor
 * @property int $vat_rate_bp
 * @property array<string, mixed> $seller_snapshot
 * @property array<string, mixed> $buyer_snapshot
 * @property string $einvoice_provider
 * @property EInvoiceStatus $einvoice_status
 * @property string|null $einvoice_document_id
 * @property string|null $zatca_uuid
 * @property string|null $qr_payload
 * @property int $einvoice_attempts
 * @property string|null $einvoice_last_error
 * @property int|null $pdf_file_id
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable|null $cleared_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'organization_id',
        'payment_id',
        'type',
        'original_invoice_id',
        'issue_date',
        'supply_date',
        'currency',
        'subtotal_minor',
        'discount_minor',
        'vat_minor',
        'total_minor',
        'vat_rate_bp',
        'seller_snapshot',
        'buyer_snapshot',
        'einvoice_provider',
        'einvoice_status',
        'einvoice_document_id',
        'zatca_uuid',
        'qr_payload',
        'einvoice_attempts',
        'einvoice_last_error',
        'pdf_file_id',
        'issued_at',
        'cleared_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'tax_invoice',
        'einvoice_status' => 'pending',
        'einvoice_attempts' => 0,
    ];

    /**
     * The buyer.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * The invoice a credit note corrects.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_invoice_id');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(self::class, 'original_invoice_id');
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function pdfFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'pdf_file_id');
    }

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'issue_date' => 'immutable_date',
            'supply_date' => 'immutable_date',
            'subtotal_minor' => MinorAmount::class,
            'discount_minor' => MinorAmount::class,
            'vat_minor' => MinorAmount::class,
            'total_minor' => MinorAmount::class,
            'vat_rate_bp' => 'integer',
            'seller_snapshot' => 'array',
            'buyer_snapshot' => 'array',
            'einvoice_status' => EInvoiceStatus::class,
            'einvoice_attempts' => 'integer',
            'issued_at' => 'immutable_datetime',
            'cleared_at' => 'immutable_datetime',
        ];
    }
}
