<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

/**
 * `PUT /me/password` (API.md §1.3). A wrong current password is the business error
 * `password_incorrect` (raised by the Action), not a field error.
 */
final class UpdatePasswordRequest extends IdentityRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', self::passwordRule()],
        ];
    }

    public function currentPassword(): string
    {
        return $this->validatedString('current_password');
    }

    public function newPassword(): string
    {
        return $this->validatedString('password');
    }

    protected function attributeKeys(): array
    {
        return ['current_password' => 'current_password', 'password' => 'password'];
    }
}
