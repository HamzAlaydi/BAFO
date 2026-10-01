<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\SponsorshipMode;
use Illuminate\Validation\Rule;

/**
 * `PUT /competitions/{competition}/sponsorship` (API.md §1.7): `mode` (none, all, selected) and
 * an optional `max_passes` cap (1–200).
 */
final class UpdateSponsorshipRequest extends BillingFormRequest
{
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
