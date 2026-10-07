<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\PresetTier;
use App\Modules\Catalog\Models\CompetitionPreset;
use App\Modules\Competitions\Enums\Direction;
use App\Modules\Competitions\Enums\Format;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetitionPreset>
 */
final class CompetitionPresetFactory extends Factory
{
    protected $model = CompetitionPreset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // The `standard_live_tender` rules of ARCHITECTURE §5.2 (RulesInput keys, no prices).
        return [
            'code' => 'preset_'.$this->faker->unique()->numerify('#####'),
            'name' => ['ar' => 'مناقصة مباشرة قياسية', 'en' => 'Standard live tender'],
            'description' => [
                'ar' => 'تحسين العرض السابق بخطوة 0.5٪ مع مؤشر التصدر وتمديد تلقائي ونافذة تسعير نهائية.',
                'en' => 'Beat your own offer by 0.5%, leading flag, auto-extension and a final pricing window.',
            ],
            'direction' => Direction::Tender,
            'format' => Format::Live,
            'tier' => null,
            'rules' => [
                'min_step_minor' => null,
                'min_step_bps' => 50,
                'amount_granularity_minor' => 100,
                'must_beat' => 'own',
                'rank_visibility' => 'leading_flag',
                'show_prices' => false,
                'auto_extend' => ['enabled' => true, 'window_seconds' => 180, 'by_seconds' => 180, 'max_extensions' => 10],
                'final_window_minutes' => 60,
                'bafo_round' => ['enabled' => false, 'duration_minutes' => null],
                'min_participants' => 2,
                'result_publication' => 'outcome_only',
            ],
            'sort_order' => $this->faker->numberBetween(1, 10),
            'is_active' => true,
        ];
    }

    /**
     * A tier preset (RELEASE_SCOPE.md §2.2): the seeded rules of that tier, no final window.
     */
    public function tier(PresetTier $tier): self
    {
        return $this->state(fn (array $attributes): array => [
            'tier' => $tier,
            'rules' => [...$attributes['rules'], 'final_window_minutes' => null, ...match ($tier) {
                PresetTier::Simple => [
                    'min_step_bps' => null,
                    'auto_extend' => ['enabled' => false, 'window_seconds' => null, 'by_seconds' => null, 'max_extensions' => null],
                    'min_participants' => 1,
                ],
                PresetTier::Standard => [],
                PresetTier::Protected => [
                    'min_step_bps' => 100,
                    'rank_visibility' => 'none',
                    'auto_extend' => ['enabled' => true, 'window_seconds' => 300, 'by_seconds' => 300, 'max_extensions' => 20],
                ],
            }],
        ]);
    }
}
