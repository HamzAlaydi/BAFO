<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

/**
 * `POST /auth/password/reset` (API.md §1.3).
 */
final class ResetPasswordRequest extends IdentityRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'confirmed', self::passwordRule()],
        ];
    }

    public function email(): string
    {
        return $this->validatedString('email');
    }

    public function code(): string
    {
        return $this->validatedString('code');
    }

    public function password(): string
    {
        return $this->validatedString('password');
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseEmail();
    }

    protected function attributeKeys(): array
    {
        return ['email' => 'email', 'code' => 'code', 'password' => 'password'];
    }
}
