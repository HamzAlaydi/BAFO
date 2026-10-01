<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /invitations/{invitation}/decline`: `reason?` (≤ 500).
 */
final class DeclineInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $value = __('competitions.attributes.reason');

        return ['reason' => is_string($value) ? $value : 'reason'];
    }

    public function reason(): ?string
    {
        $reason = $this->input('reason');

        return is_string($reason) ? $reason : null;
    }
}
