<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Database\Factories\InvoiceLineFactory;
use App\Modules\Catalog\Models\Concerns\HasTranslatedAttributes;
use App\Support\Money\Casts\MinorAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of an invoice (ARCHITECTURE §5.7 `invoice_lines`). Internal: no public id.
 *
 * @property int $id
 * @property int $invoice_id
 * @property array{ar: string, en: string} $description
 * @property int $quantity
 * @property int $unit_price_minor
 * @property int $net_minor
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class InvoiceLine extends Model
{
    /** @use HasFactory<InvoiceLineFactory> */
    use HasFactory, HasTranslatedAttributes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price_minor',
        'net_minor',
    ];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected static function newFactory(): InvoiceLineFactory
    {
        return InvoiceLineFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'description' => 'array',
            'quantity' => 'integer',
            'unit_price_minor' => MinorAmount::class,
            'net_minor' => MinorAmount::class,
        ];
    }
}
