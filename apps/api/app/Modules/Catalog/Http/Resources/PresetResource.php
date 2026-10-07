<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\CompetitionPreset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Preset (API.md §2.4): `{"id", "code", "name", "description", "direction", "format", "tier", "rules"}`.
 * `tier` is `simple`, `standard`, `protected` or null (RELEASE_SCOPE.md §2.2).
 * `rules` is the RulesInput object of API.md §2.6 without the start and reserve prices: the
 * issuer supplies prices.
 *
 * @mixin CompetitionPreset
 */
final class PresetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->translated('name'),
            'description' => $this->translated('description'),
            'direction' => $this->direction->value,
            'format' => $this->format->value,
            'tier' => $this->tier?->value,
            'rules' => self::rules($this->rules),
        ];
    }

    /**
     * Every RulesInput key except the prices, in the API.md §2.6 order; a key missing from the
     * stored JSON is null.
     *
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public static function rules(array $stored): array
    {
        $autoExtend = is_array($stored['auto_extend'] ?? null) ? $stored['auto_extend'] : [];
        $bafoRound = is_array($stored['bafo_round'] ?? null) ? $stored['bafo_round'] : [];

        return [
            'min_step_minor' => $stored['min_step_minor'] ?? null,
            'min_step_bps' => $stored['min_step_bps'] ?? null,
            'amount_granularity_minor' => $stored['amount_granularity_minor'] ?? null,
            'must_beat' => $stored['must_beat'] ?? null,
            'rank_visibility' => $stored['rank_visibility'] ?? null,
            'show_prices' => $stored['show_prices'] ?? null,
            'auto_extend' => [
                'enabled' => $autoExtend['enabled'] ?? false,
                'window_seconds' => $autoExtend['window_seconds'] ?? null,
                'by_seconds' => $autoExtend['by_seconds'] ?? null,
                'max_extensions' => $autoExtend['max_extensions'] ?? null,
            ],
            'final_window_minutes' => $stored['final_window_minutes'] ?? null,
            'bafo_round' => [
                'enabled' => $bafoRound['enabled'] ?? false,
                'duration_minutes' => $bafoRound['duration_minutes'] ?? null,
            ],
            'min_participants' => $stored['min_participants'] ?? null,
            'result_publication' => $stored['result_publication'] ?? null,
        ];
    }
}
