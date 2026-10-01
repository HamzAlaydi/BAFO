<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Database\Factories\PlanFactory;
use App\Modules\Catalog\Models\Concerns\HasTranslatedAttributes;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Money\Casts\MinorAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An issuer plan (ARCHITECTURE §5.7 `plans`): `single`, `plus`, `pro`, `custom`. Prices exclude
 * VAT; everything is admin-editable.
 *
 * @property int $id
 * @property string $public_id
 * @property string $code
 * @property array{ar: string, en: string} $name
 * @property array{ar: string, en: string}|null $description
 * @property list<array{ar: string, en: string}> $features
 * @property int|null $seats
 * @property int|null $monthly_price_minor
 * @property int|null $annual_price_minor
 * @property int|null $monthly_list_price_minor
 * @property int|null $annual_list_price_minor
 * @property bool $is_custom
 * @property bool $is_featured
 * @property bool $is_active
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasPublicId, HasTranslatedAttributes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'features',
        'seats',
        'monthly_price_minor',
        'annual_price_minor',
        'monthly_list_price_minor',
        'annual_list_price_minor',
        'is_custom',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'features' => '[]',
    ];

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    protected static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'features' => 'array',
            'seats' => 'integer',
            'monthly_price_minor' => MinorAmount::class,
            'annual_price_minor' => MinorAmount::class,
            'monthly_list_price_minor' => MinorAmount::class,
            'annual_list_price_minor' => MinorAmount::class,
            'is_custom' => 'boolean',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
