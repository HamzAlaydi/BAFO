<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\CategoryFactory;
use App\Modules\Catalog\Models\Concerns\HasTranslatedAttributes;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Models\Vendor;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A competition / supplier category (ARCHITECTURE §5.2 `categories`).
 *
 * `is_other` requires `competitions.category_other_text`; `auction_allowed = false` blocks auctions
 * (R5 guardrail, §7.2 R14).
 *
 * @property int $id
 * @property string $public_id
 * @property string $code
 * @property array{ar: string, en: string} $name
 * @property bool $is_other
 * @property bool $auction_allowed
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasPublicId, HasTranslatedAttributes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'is_other',
        'auction_allowed',
        'sort_order',
        'is_active',
    ];

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_category');
    }

    /**
     * @return BelongsToMany<Vendor, $this>
     */
    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(Vendor::class, 'vendor_category');
    }

    /**
     * @return HasMany<Competition, $this>
     */
    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class);
    }

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'is_other' => 'boolean',
            'auction_allowed' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
