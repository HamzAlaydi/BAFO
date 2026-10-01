<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CompetitionPreset;
use App\Modules\Competitions\Data\CompetitionInput;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\Organization;
use Illuminate\Validation\ValidationException;

/**
 * Applies a create or draft-update request to a competition model: preset rules, column
 * defaults, the enabled/disabled normalisation of the nested rule groups, then the §7.2 save
 * checks. The model is not saved.
 */
final readonly class CompetitionComposer
{
    public function __construct(private RulesValidator $validator) {}

    public function compose(Competition $competition, CompetitionInput $input, bool $creating): void
    {
        $rulesSent = array_intersect_key($input->columns, RulesMapper::PATHS);
        $other = array_diff_key($input->columns, RulesMapper::PATHS);

        $competition->forceFill($other);

        if ($input->has('preset_code')) {
            $competition->preset_code = $input->presetCode;
        }

        $preset = $input->presetCode !== null ? $this->preset($input->presetCode, $competition) : null;
        $presetColumns = $preset !== null ? array_intersect_key(RulesMapper::toColumns($preset->rules), RulesMapper::PATHS) : [];

        // Prices never come from a preset (API.md §2.4).
        unset($presetColumns['start_price_minor'], $presetColumns['reserve_price_minor']);

        $base = $creating ? RulesMapper::defaultsFor($competition->format) : [];

        $competition->forceFill([...$base, ...$presetColumns, ...$rulesSent]);

        $this->normalise($competition);
        $this->loadRelations($competition);

        $this->validator->validateSave($competition);
    }

    /**
     * A disabled auto-extension or BAFO round carries no parameters; an enabled BAFO round
     * defaults to 60 minutes (§7.2 R10, R12).
     *
     * CONTRACT-GAP: R10 says the three auto-extend fields are null when it is off; the values
     * are cleared rather than rejected, so a client can switch the option off in one field.
     */
    private function normalise(Competition $competition): void
    {
        if (! $competition->auto_extend_enabled) {
            $competition->auto_extend_window_seconds = null;
            $competition->auto_extend_by_seconds = null;
            $competition->auto_extend_max = null;
        }

        if (! $competition->bafo_round_enabled) {
            $competition->bafo_duration_minutes = null;
        } elseif ($competition->bafo_duration_minutes === null) {
            $competition->bafo_duration_minutes = 60;
        }
    }

    private function preset(string $code, Competition $competition): CompetitionPreset
    {
        $preset = CompetitionPreset::query()->where('code', $code)->where('is_active', true)->first();

        if ($preset === null || $preset->direction !== $competition->direction || $preset->format !== $competition->format) {
            $message = __('competitions.validation.preset_mismatch');

            throw ValidationException::withMessages(['preset_code' => [is_string($message) ? $message : 'preset_mismatch']]);
        }

        return $preset;
    }

    private function loadRelations(Competition $competition): void
    {
        if (! $competition->relationLoaded('organization') || $competition->organization?->id !== $competition->organization_id) {
            $competition->setRelation('organization', Organization::query()->find($competition->organization_id));
        }

        if (! $competition->relationLoaded('category') || $competition->category?->id !== $competition->category_id) {
            $competition->setRelation('category', Category::query()->find($competition->category_id));
        }
    }
}
