<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Database\Factories\PaymentLineFactory;
use App\Modules\Billing\Enums\PaymentLineKind;
use App\Modules\Catalog\Models\Concerns\HasTranslatedAttributes;
use App\Support\Money\Casts\MinorAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A line of a payment (ARCHITECTURE §5.7 `payment_lines`): `net = quantity × unit`. `ref_type`
 * is an optional morph alias. Internal: no public id.
 *
 * @property int $id
 * @property int $payment_id
 * @property PaymentLineKind $kind
 * @property array{ar: string, en: string} $description
 * @property int $quantity
 * @property int $unit_price_minor
 * @property int $net_minor
 * @property string|null $ref_type
 * @property int|null $ref_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class PaymentLine extends Model
{
    /** @use HasFactory<PaymentLineFactory> */
    use HasFactory, HasTranslatedAttributes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_id',
        'kind',
        'description',
        'quantity',
        'unit_price_minor',
        'net_minor',
        'ref_type',
        'ref_id',
    ];

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function ref(): MorphTo
    {
        return $this->morphTo('ref');
    }

    protected static function newFactory(): PaymentLineFactory
    {
        return PaymentLineFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PaymentLineKind::class,
            'description' => 'array',
            'quantity' => 'integer',
            'unit_price_minor' => MinorAmount::class,
            'net_minor' => MinorAmount::class,
            'ref_id' => 'integer',
        ];
    }
}
