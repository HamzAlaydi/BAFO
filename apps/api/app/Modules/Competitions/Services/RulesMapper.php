<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Competitions\Enums\Format;
use App\Modules\Competitions\Models\Competition;

/**
 * Maps the `RulesInput` / `Rules` object of API.md §2.6 to the typed competition columns
 * (ARCHITECTURE D6, §5.5) and back.
 */
final class RulesMapper
{
    /**
     * Column => the API path of the field (validation error keys).
     *
     * @var array<string, string>
     */
    public const array PATHS = [
        'start_price_minor' => 'rules.start_price_minor',
        'reserve_price_minor' => 'rules.reserve_price_minor',
        'min_step_minor' => 'rules.min_step_minor',
        'min_step_bps' => 'rules.min_step_bps',
        'amount_granularity_minor' => 'rules.amount_granularity_minor',
        'must_beat' => 'rules.must_beat',
        'rank_visibility' => 'rules.rank_visibility',
        'show_prices' => 'rules.show_prices',
        'auto_extend_enabled' => 'rules.auto_extend.enabled',
        'auto_extend_window_seconds' => 'rules.auto_extend.window_seconds',
        'auto_extend_by_seconds' => 'rules.auto_extend.by_seconds',
        'auto_extend_max' => 'rules.auto_extend.max_extensions',
        'final_window_minutes' => 'rules.final_window_minutes',
        'bafo_round_enabled' => 'rules.bafo_round.enabled',
        'bafo_duration_minutes' => 'rules.bafo_round.duration_minutes',
        'min_participants' => 'rules.min_participants',
        'result_publication' => 'rules.result_publication',
    ];

    /**
     * The column defaults of §5.5 for every rules column.
     *
     * @var array<string, mixed>
     */
    public const array COLUMN_DEFAULTS = [
        'start_price_minor' => null,
        'reserve_price_minor' => null,
        'min_step_minor' => null,
        'min_step_bps' => null,
        'amount_granularity_minor' => 100,
        'must_beat' => null,
        'rank_visibility' => 'leading_flag',
        'show_prices' => false,
        'auto_extend_enabled' => false,
        'auto_extend_window_seconds' => null,
        'auto_extend_by_seconds' => null,
        'auto_extend_max' => null,
        'final_window_minutes' => null,
        'bafo_round_enabled' => false,
        'bafo_duration_minutes' => null,
        'min_participants' => 2,
        'result_publication' => 'outcome_only',
    ];

    /**
     * Columns for the rules keys present in `$rules` (a RulesInput, possibly partial).
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public static function toColumns(array $rules): array
    {
        $columns = [];

        foreach (['start_price_minor', 'reserve_price_minor', 'min_step_minor', 'min_step_bps', 'amount_granularity_minor',
            'must_beat', 'rank_visibility', 'show_prices', 'final_window_minutes', 'min_participants', 'result_publication'] as $key) {
            if (array_key_exists($key, $rules)) {
                $columns[$key] = $rules[$key];
            }
        }

        $autoExtend = $rules['auto_extend'] ?? null;

        if (is_array($autoExtend)) {
            foreach (['enabled' => 'auto_extend_enabled', 'window_seconds' => 'auto_extend_window_seconds',
                'by_seconds' => 'auto_extend_by_seconds', 'max_extensions' => 'auto_extend_max'] as $key => $column) {
                if (array_key_exists($key, $autoExtend)) {
                    $columns[$column] = $autoExtend[$key];
                }
            }
        }

        $bafo = $rules['bafo_round'] ?? null;

        if (is_array($bafo)) {
            foreach (['enabled' => 'bafo_round_enabled', 'duration_minutes' => 'bafo_duration_minutes'] as $key => $column) {
                if (array_key_exists($key, $bafo)) {
                    $columns[$column] = $bafo[$key];
                }
            }
        }

        return $columns;
    }

    /**
     * Defaults for the rules columns a create request (and its preset) left out: the §5.5 column
     * defaults, adjusted to the format so that they satisfy R1 and R2.
     *
     * CONTRACT-GAP: API.md §1.4 says absent keys "take the column defaults"; the raw defaults
     * would fail R1 for a sealed competition (rank_visibility leading_flag) and R2 for a live one
     * (must_beat null), so a sealed competition defaults to rank_visibility none and a live one to
     * must_beat own.
     *
     * @return array<string, mixed>
     */
    public static function defaultsFor(Format $format): array
    {
        return $format === Format::Sealed
            ? [...self::COLUMN_DEFAULTS, 'rank_visibility' => 'none']
            : [...self::COLUMN_DEFAULTS, 'must_beat' => 'own'];
    }

    /**
     * The Rules object of API.md §2.6. `reserve_price_minor` is issuer-only: omitted otherwise.
     *
     * @return array<string, mixed>
     */
    public static function toRules(Competition $competition, bool $withReserve): array
    {
        $rules = ['start_price_minor' => $competition->start_price_minor];

        if ($withReserve) {
            $rules['reserve_price_minor'] = $competition->reserve_price_minor;
        }

        return [
            ...$rules,
            'min_step_minor' => $competition->min_step_minor,
            'min_step_bps' => $competition->min_step_bps,
            'amount_granularity_minor' => $competition->amount_granularity_minor,
            'must_beat' => $competition->must_beat?->value,
            'rank_visibility' => $competition->rank_visibility->value,
            'show_prices' => $competition->show_prices,
            'auto_extend' => [
                'enabled' => $competition->auto_extend_enabled,
                'window_seconds' => $competition->auto_extend_window_seconds,
                'by_seconds' => $competition->auto_extend_by_seconds,
                'max_extensions' => $competition->auto_extend_max,
            ],
            'final_window_minutes' => $competition->final_window_minutes,
            'bafo_round' => [
                'enabled' => $competition->bafo_round_enabled,
                'duration_minutes' => $competition->bafo_duration_minutes,
            ],
            'min_participants' => $competition->min_participants,
            'result_publication' => $competition->result_publication->value,
        ];
    }

    public static function pathOf(string $column): string
    {
        return self::PATHS[$column] ?? $column;
    }
}
