<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

/**
 * `POST /invitations/decline` (guest): `token`, `reason?` (≤ 500).
 */
final class DeclineInvitationByTokenRequest extends InvitationTokenRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:200'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function reason(): ?string
    {
        $reason = $this->input('reason');

        return is_string($reason) ? $reason : null;
    }
}
