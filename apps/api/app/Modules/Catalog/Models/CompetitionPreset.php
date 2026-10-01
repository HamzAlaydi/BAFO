<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\CompetitionPresetFactory;
use App\Modules\Catalog\Models\Concerns\HasTranslatedAttributes;
use App\Modules\Competitions\Enums\Direction;
use App\Modules\Competitions\Enums\Format;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A rules preset (ARCHITECTURE §5.2 `competition_presets`). `rules` holds the RulesInput keys of
 * API.md §2.6 without prices; the issuer supplies the prices.
 *
 * @property int $id
 * @property string $public_id
 * @property string $code
 * @property array{ar: string, en: string} $name
 * @property array{ar: string, en: string} $description
 * @property Direction $direction
 * @property Format $format
 * @property array<string, mixed> $rules
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class CompetitionPreset extends Model
{
    /** @use HasFactory<CompetitionPresetFactory> */
    use HasFactory, HasPublicId, HasTranslatedAttributes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'direction',
        'format',
        'rules',
        'sort_order',
        'is_active',
    ];

    protected static function newFactory(): CompetitionPresetFactory
    {
        return CompetitionPresetFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'direction' => Direction::class,
            'format' => Format::class,
            'rules' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
