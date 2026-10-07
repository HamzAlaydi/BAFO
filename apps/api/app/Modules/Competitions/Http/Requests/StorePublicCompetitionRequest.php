<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

use App\Modules\Billing\Enums\SponsorshipMode;
use App\Modules\Competitions\Http\Requests\Concerns\ValidatesInvitationRows;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Public `POST /competitions` (API.md §3.4): the app create body with `category_code` /
 * `region_code`, plus the optional `external_refs`, `invitations` (InvitationInput rows) and
 * `sponsorship` `{mode, max_passes}`.
 */
final class StorePublicCompetitionRequest extends CompetitionRequest
{
    use ValidatesInvitationRows;

    protected function creating(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            ...$this->invitationRules(required: false),
            'sponsorship' => ['sometimes', 'nullable', 'array'],
            'sponsorship.mode' => ['required_with:sponsorship', Rule::in(['none', 'all', 'selected'])],
            'sponsorship.max_passes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            fn (Validator $validator) => $this->checkInvitationTargets($validator),
            fn (Validator $validator) => $this->refuseHiddenInvitationFields($validator),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...parent::attributes(), ...$this->invitationAttributes()];
    }

    public function hasSponsorship(): bool
    {
        return is_array($this->validated('sponsorship'));
    }

    public function sponsorshipMode(): ?SponsorshipMode
    {
        $mode = $this->validated('sponsorship.mode');

        return is_string($mode) ? SponsorshipMode::tryFrom($mode) : null;
    }

    public function maxPasses(): ?int
    {
        $max = $this->validated('sponsorship.max_passes');

        return is_numeric($max) ? (int) $max : null;
    }
}
