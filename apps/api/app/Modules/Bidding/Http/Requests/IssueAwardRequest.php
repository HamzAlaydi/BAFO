<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Requests;

use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST /competitions/{competition}/award (API.md §1.6). The justification reason is an active
 * close reason of kind `award_justification`; its text is required when the reason
 * `requires_note`.
 */
final class IssueAwardRequest extends BiddingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'participant_id' => ['required', 'string', 'max:26'],
            'justification_reason_id' => [
                'nullable',
                'string',
                Rule::exists('close_reasons', 'public_id')
                    ->where('kind', CloseReasonKind::AwardJustification->value)
                    ->where('is_active', true),
            ],
            'justification_text' => ['nullable', 'string', 'max:2000'],
            'confirm_reserve_not_met' => ['sometimes', 'nullable', 'boolean'],
            'message_to_winner' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $reason = $this->justificationReason();

                if ($reason !== null && $reason->requires_note && blank($this->input('justification_text'))) {
                    $validator->errors()->add('justification_text', (string) __('bidding.validation.justification_text_required'));
                }
            },
        ];
    }

    public function participantId(): string
    {
        return (string) $this->validated('participant_id');
    }

    public function justificationReason(): ?CloseReason
    {
        $id = $this->input('justification_reason_id');

        if (! is_string($id) || $id === '') {
            return null;
        }

        return CloseReason::query()
            ->where('public_id', strtolower($id))
            ->where('kind', CloseReasonKind::AwardJustification->value)
            ->where('is_active', true)
            ->first();
    }

    public function justificationText(): ?string
    {
        return $this->stringOrNull('justification_text');
    }

    public function confirmReserveNotMet(): bool
    {
        return $this->boolean('confirm_reserve_not_met');
    }

    public function messageToWinner(): ?string
    {
        return $this->stringOrNull('message_to_winner');
    }

    public function internalNotes(): ?string
    {
        return $this->stringOrNull('internal_notes');
    }
}
