<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

/**
 * `POST /auth/team-invitations/lookup` (API.md §1.3). The token comes from the link fragment.
 */
final class LookupTeamInvitationRequest extends IdentityRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
        ];
    }

    public function token(): string
    {
        return $this->validatedString('token');
    }

    protected function attributeKeys(): array
    {
        return ['token' => 'token'];
    }
}
