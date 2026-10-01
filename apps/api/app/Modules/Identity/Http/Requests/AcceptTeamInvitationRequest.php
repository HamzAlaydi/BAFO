<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

/**
 * `POST /auth/team-invitations/accept` (API.md §1.3).
 */
final class AcceptTeamInvitationRequest extends IdentityRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', self::passwordRule()],
            'device_name' => ['required', 'string', 'max:120'],
            'accept_terms' => ['required', 'accepted'],
        ];
    }

    public function token(): string
    {
        return $this->validatedString('token');
    }

    public function password(): string
    {
        return $this->validatedString('password');
    }

    public function deviceName(): string
    {
        return $this->validatedString('device_name');
    }

    protected function attributeKeys(): array
    {
        return ['token' => 'token', 'password' => 'password', 'device_name' => 'device_name', 'accept_terms' => 'accept_terms'];
    }
}
