<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\SponsorshipMode;
use App\Support\Features\Feature;
use App\Support\Features\FeatureFlags;
use App\Support\Features\FeatureRefusals;
use Illuminate\Validation\Rule;

/**
 * `PUT /competitions/{competition}/sponsorship` (API.md §1.7): `mode` (none, all, selected) and
 * an optional `max_passes` cap (1–200). While the release scope hides `sponsorship`
 * (RELEASE_SCOPE.md §1.5) only `mode: none` is accepted (turning covered fees off stays possible
 * on an existing record); `all` or `selected` is 404 `feature_disabled`.
 */
final class UpdateSponsorshipRequest extends BillingFormRequest
{
    protected function prepareForValidation(): void
    {
        $mode = $this->input('mode');

        if (is_string($mode) && SponsorshipMode::tryFrom($mode) !== null
            && ! app(FeatureFlags::class)->enabled(Feature::Sponsorship)) {
            throw FeatureRefusals::disabled(Feature::Sponsorship);
        }
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in(['none', 'all', 'selected'])],
            'max_passes' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * The mode, or null for none.
     */
    public function mode(): ?SponsorshipMode
    {
        return SponsorshipMode::tryFrom((string) $this->validated('mode'));
    }

    public function maxPasses(): ?int
    {
        $max = $this->validated('max_passes');

        return is_numeric($max) ? (int) $max : null;
    }
}
