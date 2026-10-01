<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

/**
 * `POST /invitations/lookup` (guest): `token`, plus the `website_url` honeypot (API.md §0.8).
 */
final class LookupInvitationRequest extends InvitationTokenRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:200'],
            'website_url' => ['prohibited'],
        ];
    }
}
