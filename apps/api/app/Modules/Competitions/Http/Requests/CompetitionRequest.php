<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CompetitionPreset;
use App\Modules\Catalog\Models\Region;
use App\Modules\Catalog\Queries\Lookups;
use App\Modules\Competitions\Data\CompetitionInput;
use App\Modules\Competitions\Enums\Format;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\RulesMapper;
use App\Support\Features\Feature;
use App\Support\Features\FeatureFlags;
use App\Support\Features\FeatureRefusals;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The shared body of `POST /competitions` and `PATCH /competitions/{competition}` (API.md §1.4),
 * app and public API alike. The public API sends `category_code` / `region_code` (translated to
 * ids in prepareForValidation) and may send `external_refs`.
 *
 * Only the field format is checked here; the §7.2 rules run in the Actions (RulesValidator).
 * The release scope (RELEASE_SCOPE.md §1.5) adds the field-level refusals of hidden features
 * (refuseHiddenFeatures()) and, while `advanced_rules` is off, makes `preset_code` required on
 * create (its values fill the advanced keys).
 */
abstract class CompetitionRequest extends FormRequest
{
    /**
     * Whether the base fields are required (create) or optional (update).
     */
    abstract protected function creating(): bool;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['category_id', 'region_id'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $merge[$field] = strtolower(trim($value));
            }
        }

        $categoryCode = $this->input('category_code');

        if (is_string($categoryCode) && ! $this->filled('category_id')) {
            $merge['category_id'] = Category::query()->where('code', $categoryCode)->where('is_active', true)->value('public_id') ?? '';
        }

        $regionCode = $this->input('region_code');

        if (is_string($regionCode) && ! $this->filled('region_id')) {
            $merge['region_id'] = Region::query()->where('code', strtoupper($regionCode))->where('is_active', true)->value('public_id') ?? '';
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->creating() ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'category_id' => [$required, 'string', Rule::exists('categories', 'public_id')->where('is_active', true)],
            'category_code' => ['sometimes', 'string', 'max:40'],
            'category_other_text' => ['sometimes', 'nullable', 'string', 'max:150'],
            'region_id' => [$required, 'string', Rule::exists('regions', 'public_id')->where('is_active', true)],
            'region_code' => ['sometimes', 'string', 'max:8'],
            'direction' => [$required, Rule::in(['tender', 'auction'])],
            'format' => [$required, Rule::in(['live', 'sealed'])],
            'preset_code' => [
                Rule::requiredIf(fn (): bool => $this->creating() && ! app(FeatureFlags::class)->enabled(Feature::AdvancedRules)),
                'nullable', 'string', 'max:60',
            ],
            'rules' => ['sometimes', 'array'],
            'rules.start_price_minor' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'rules.reserve_price_minor' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'rules.min_step_minor' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'rules.min_step_bps' => ['sometimes', 'nullable', 'integer'],
            'rules.amount_granularity_minor' => ['sometimes', 'integer', Rule::in([1, 100])],
            'rules.must_beat' => ['sometimes', 'nullable', Rule::in(['own', 'best'])],
            'rules.rank_visibility' => ['sometimes', Rule::in(['full', 'leading_flag', 'none'])],
            'rules.show_prices' => ['sometimes', 'boolean'],
            'rules.auto_extend' => ['sometimes', 'array'],
            'rules.auto_extend.enabled' => ['sometimes', 'boolean'],
            'rules.auto_extend.window_seconds' => ['sometimes', 'nullable', 'integer'],
            'rules.auto_extend.by_seconds' => ['sometimes', 'nullable', 'integer'],
            'rules.auto_extend.max_extensions' => ['sometimes', 'nullable', 'integer'],
            'rules.final_window_minutes' => ['sometimes', 'nullable', 'integer'],
            'rules.bafo_round' => ['sometimes', 'array'],
            'rules.bafo_round.enabled' => ['sometimes', 'boolean'],
            'rules.bafo_round.duration_minutes' => ['sometimes', 'nullable', 'integer'],
            'rules.min_participants' => ['sometimes', 'integer'],
            'rules.result_publication' => ['sometimes', Rule::in(['none', 'outcome_only', 'outcome_and_amount'])],
            'bidding_opens_at' => ['sometimes', 'nullable', 'date'],
            'scheduled_close_at' => ['sometimes', 'nullable', 'date'],
            'external_refs' => ['sometimes', 'array', 'max:20'],
            'external_refs.*.system' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+(:[a-z0-9_]+)?$/'],
            'external_refs.*.type' => ['required', 'string', 'max:60'],
            'external_refs.*.id' => ['required', 'string', 'max:120'],
            'external_refs.*.number' => ['sometimes', 'nullable', 'string', 'max:120'],
            'external_refs.*.url' => ['sometimes', 'nullable', 'url', 'max:500'],
        ];
    }

    /**
     * API.md §1.4: `category_other_text` is required when the category is "Other" (enforced
     * again at publish, R15).
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $categoryId = $this->input('category_id');

                if (! is_string($categoryId) || $validator->errors()->has('category_id')) {
                    return;
                }

                $isOther = Category::query()->where('public_id', $categoryId)->value('is_other');
                $text = $this->input('category_other_text');

                if ($isOther === true && (! is_string($text) || trim($text) === '')) {
                    $validator->errors()->add('category_other_text', $this->trans('competitions.validation.category_other_required'));
                }
            },
            fn (Validator $validator) => $this->refuseHiddenFeatures($validator),
        ];
    }

    /**
     * RELEASE_SCOPE.md §1.5 field-level refusals: a value that configures a feature the release
     * scope hides is a 422 with `errors.feature_disabled_field` on its path.
     *
     * | Flag off               | Refused value                                                    |
     * |------------------------|------------------------------------------------------------------|
     * | `sealed_format`        | `format = sealed`                                                |
     * | `bafo_round`           | `rules.bafo_round.enabled = true`                                |
     * | `final_pricing_window` | `rules.final_window_minutes != null`                             |
     * | `advanced_rules`       | `rules.reserve_price_minor != null`, `rules.amount_granularity_minor = 1`, `rules.result_publication` / `rules.min_participants` other than the preset's value |
     * | (any of the above)     | a `preset_code` that `GET /lookups` does not offer (Lookups::presetAvailable) |
     *
     * Existing records keep saving (§0): on `PATCH` a value the competition already has is
     * accepted, so a draft created while `full` still saves its other fields with the whole
     * `rules` object (SCREENS G4); only a change towards a hidden value is refused. The preset
     * of reference is the one the request sends, else the competition's.
     */
    private function refuseHiddenFeatures(Validator $validator): void
    {
        $features = app(FeatureFlags::class);
        $route = $this->creating() ? null : $this->route('competition');
        $current = $route instanceof Competition ? $route : null;
        $rules = $this->input('rules');
        $rules = is_array($rules) ? $rules : [];
        $bafoRound = is_array($rules['bafo_round'] ?? null) ? $rules['bafo_round'] : [];

        if (! $features->enabled(Feature::SealedFormat)
            && $this->input('format') === Format::Sealed->value && $current?->format !== Format::Sealed) {
            FeatureRefusals::refuseField($validator, 'format');
        }

        if (! $features->enabled(Feature::BafoRound)
            && self::isTrue($bafoRound['enabled'] ?? null) && $current?->bafo_round_enabled !== true) {
            FeatureRefusals::refuseField($validator, 'rules.bafo_round.enabled');
        }

        if (! $features->enabled(Feature::FinalPricingWindow)
            && ($rules['final_window_minutes'] ?? null) !== null
            && ($current === null || self::differs($rules['final_window_minutes'], $current->final_window_minutes))) {
            FeatureRefusals::refuseField($validator, 'rules.final_window_minutes');
        }

        $presetCode = $this->input('preset_code');

        if (is_string($presetCode) && $presetCode !== '' && $presetCode !== $current?->preset_code) {
            $preset = CompetitionPreset::query()->where('code', $presetCode)->first();

            if ($preset !== null && ! app(Lookups::class)->presetAvailable($preset)) {
                FeatureRefusals::refuseField($validator, 'preset_code');
            }
        }

        if (! $features->enabled(Feature::AdvancedRules)) {
            $this->refuseAdvancedRules($validator, $rules, $current);
        }
    }

    /**
     * The `advanced_rules` row of refuseHiddenFeatures().
     *
     * @param  array<array-key, mixed>  $rules
     */
    private function refuseAdvancedRules(Validator $validator, array $rules, ?Competition $current): void
    {
        if (($rules['reserve_price_minor'] ?? null) !== null
            && ($current === null || self::differs($rules['reserve_price_minor'], $current->reserve_price_minor))) {
            FeatureRefusals::refuseField($validator, 'rules.reserve_price_minor');
        }

        if (array_key_exists('amount_granularity_minor', $rules) && ! self::differs($rules['amount_granularity_minor'], 1)
            && $current?->amount_granularity_minor !== 1) {
            FeatureRefusals::refuseField($validator, 'rules.amount_granularity_minor');
        }

        // The preset of reference: the one sent (even null, which clears it), else the competition's.
        $code = $this->has('preset_code') ? $this->input('preset_code') : $current?->preset_code;
        $preset = is_string($code) && $code !== '' ? CompetitionPreset::query()->where('code', $code)->first() : null;

        if (is_string($code) && $code !== '' && $preset === null) {
            return; // An unknown preset: CompetitionComposer answers `preset_mismatch` on preset_code.
        }

        $presetRules = $preset !== null ? $preset->rules : [];

        foreach (['result_publication' => $current?->result_publication->value, 'min_participants' => $current?->min_participants] as $key => $stored) {
            if (! array_key_exists($key, $rules)) {
                continue;
            }

            $presetValue = array_key_exists($key, $presetRules) && ! self::differs($rules[$key], $presetRules[$key]);

            if (! $presetValue && ($current === null || self::differs($rules[$key], $stored))) {
                FeatureRefusals::refuseField($validator, 'rules.'.$key);
            }
        }
    }

    private static function isTrue(mixed $value): bool
    {
        return in_array($value, [true, 1, '1'], true);
    }

    /**
     * Whether a sent value differs from a stored one (integers compare by value, so `"60"` equals `60`).
     */
    private static function differs(mixed $sent, mixed $stored): bool
    {
        if (is_numeric($sent) && is_numeric($stored)) {
            return (int) $sent !== (int) $stored;
        }

        return $sent !== $stored;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (['title', 'description', 'category_id', 'category_code', 'category_other_text', 'region_id', 'region_code',
            'direction', 'format', 'preset_code', 'rules', 'bidding_opens_at', 'scheduled_close_at', 'external_refs'] as $field) {
            $attributes[$field] = $this->trans('competitions.attributes.'.$field);
        }

        foreach (RulesMapper::PATHS as $column => $path) {
            $attributes[$path] = $this->trans('competitions.attributes.'.$column);
        }

        return $attributes;
    }

    public function toInput(): CompetitionInput
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();
        $columns = [];

        foreach (['title', 'description', 'category_other_text', 'direction', 'format'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                $columns[$field] = is_string($value) && in_array($field, ['title', 'description', 'category_other_text'], true)
                    ? (trim($value) === '' && $field !== 'title' ? null : trim($value))
                    : $value;
            }
        }

        if (array_key_exists('category_id', $data)) {
            $columns['category_id'] = Category::query()->where('public_id', $data['category_id'])->value('id');
        }

        if (array_key_exists('region_id', $data)) {
            $columns['region_id'] = Region::query()->where('public_id', $data['region_id'])->value('id');
        }

        foreach (['bidding_opens_at', 'scheduled_close_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $columns[$field] = is_string($data[$field]) ? CarbonImmutable::parse($data[$field])->utc() : null;
            }
        }

        if (isset($data['rules']) && is_array($data['rules'])) {
            /** @var array<string, mixed> $rules */
            $rules = $data['rules'];
            $columns = [...$columns, ...RulesMapper::toColumns($rules)];
        }

        $fields = array_values(array_filter(
            array_keys($this->all()),
            static fn (string $key): bool => $key !== 'website_url',
        ));

        $presetCode = $data['preset_code'] ?? null;

        return new CompetitionInput(
            columns: $columns,
            fields: $fields,
            presetCode: is_string($presetCode) && $presetCode !== '' ? $presetCode : null,
            externalRefs: isset($data['external_refs']) && is_array($data['external_refs']) ? $this->externalRefs($data['external_refs']) : null,
        );
    }

    /**
     * @param  array<array-key, mixed>  $refs
     * @return list<array{system: string, type: string, id: string, number: string|null, url: string|null}>
     */
    private function externalRefs(array $refs): array
    {
        $list = [];

        foreach ($refs as $ref) {
            if (! is_array($ref)) {
                continue;
            }

            $list[] = [
                'system' => (string) $ref['system'],
                'type' => (string) $ref['type'],
                'id' => (string) $ref['id'],
                'number' => isset($ref['number']) ? (string) $ref['number'] : null,
                'url' => isset($ref['url']) ? (string) $ref['url'] : null,
            ];
        }

        return $list;
    }

    protected function trans(string $key): string
    {
        $value = __($key);

        return is_string($value) ? $value : $key;
    }
}
