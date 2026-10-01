<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Catalog\Models\CompetitionPreset;
use App\Modules\Catalog\Models\Region;
use Illuminate\Database\Eloquent\Collection;

/**
 * The active lookup rows, ordered by `sort_order` (API.md §1.2, §3.2).
 */
final class Lookups
{
    public const string REGIONS = 'regions';

    public const string CATEGORIES = 'categories';

    public const string CLOSE_REASONS = 'close-reasons';

    public const string PRESETS = 'presets';

    /**
     * The `{type}` values of `GET /api/app/v1/lookups/{type}`.
     *
     * @var list<string>
     */
    public const array APP_TYPES = [self::REGIONS, self::CATEGORIES, self::CLOSE_REASONS, self::PRESETS];

    /**
     * The `{type}` values of `GET /api/public/v1/lookups/{type}` (no presets on the public API).
     *
     * @var list<string>
     */
    public const array PUBLIC_TYPES = [self::REGIONS, self::CATEGORIES, self::CLOSE_REASONS];

    /**
     * @return Collection<int, Region>
     */
    public function regions(): Collection
    {
        return Region::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        return Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * @return Collection<int, CloseReason>
     */
    public function closeReasons(?CloseReasonKind $kind = null): Collection
    {
        return CloseReason::query()
            ->where('is_active', true)
            ->when($kind !== null, static fn ($query) => $query->where('kind', $kind?->value))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, CompetitionPreset>
     */
    public function presets(): Collection
    {
        return CompetitionPreset::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
    }
}
