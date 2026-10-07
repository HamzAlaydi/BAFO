<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Catalog\Models\CompetitionPreset;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Enums\Format;
use App\Support\Features\Feature;
use App\Support\Features\FeatureFlags;
use Illuminate\Database\Eloquent\Collection;

/**
 * The active lookup rows, ordered by `sort_order` (API.md §1.2, §3.2). Presets are also filtered
 * by the release scope (RELEASE_SCOPE.md §1.5): a preset that configures a hidden feature is not
 * offered.
 */
final readonly class Lookups
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

    public function __construct(private FeatureFlags $features) {}

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
     * The active presets the release scope offers (presetAvailable()).
     *
     * @return Collection<int, CompetitionPreset>
     */
    public function presets(): Collection
    {
        return CompetitionPreset::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (CompetitionPreset $preset): bool => $this->presetAvailable($preset))
            ->values();
    }

    /**
     * Whether the release scope offers this preset (RELEASE_SCOPE.md §1.5, §2.2): sealed presets need
     * `sealed_format`, presets with a BAFO round `bafo_round`, presets with a final window
     * `final_pricing_window`, and untiered presets `advanced_rules`. `GET /lookups` and the
     * competition requests (`preset_code`) share this rule.
     */
    public function presetAvailable(CompetitionPreset $preset): bool
    {
        $rules = $preset->rules;
        $bafoRound = is_array($rules['bafo_round'] ?? null) ? $rules['bafo_round'] : [];

        return match (true) {
            $preset->format === Format::Sealed && ! $this->features->enabled(Feature::SealedFormat),
            ($bafoRound['enabled'] ?? false) === true && ! $this->features->enabled(Feature::BafoRound),
            ($rules['final_window_minutes'] ?? null) !== null && ! $this->features->enabled(Feature::FinalPricingWindow),
            $preset->tier === null && ! $this->features->enabled(Feature::AdvancedRules) => false,
            default => true,
        };
    }
}
