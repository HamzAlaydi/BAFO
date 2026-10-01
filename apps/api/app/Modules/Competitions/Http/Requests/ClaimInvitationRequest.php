<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

/**
 * `POST /invitations/claim`: `token`, `code?` (6 digits, the OTP sent to the invited e-mail).
 */
final class ClaimInvitationRequest extends InvitationTokenRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'digits:6'],
        ];
    }

    public function code(): ?string
    {
        $code = $this->input('code');

        return is_string($code) && $code !== '' ? $code : null;
    }
}
