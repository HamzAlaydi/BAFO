<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\RegionFactory;
use App\Modules\Catalog\Models\Concerns\HasTranslatedAttributes;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Models\Vendor;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Saudi region (ARCHITECTURE §5.2 `regions`; 13 seeded by CatalogReferenceSeeder).
 *
 * @property int $id
 * @property string $public_id
 * @property string $code
 * @property array{ar: string, en: string} $name
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory, HasPublicId, HasTranslatedAttributes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'sort_order',
        'is_active',
    ];

    /**
     * @return HasMany<Organization, $this>
     */
    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    /**
     * @return HasMany<Vendor, $this>
     */
    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    /**
     * @return HasMany<Competition, $this>
     */
    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class);
    }

    protected static function newFactory(): RegionFactory
    {
        return RegionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
