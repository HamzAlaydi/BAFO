<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

/**
 * `POST /auth/login` (API.md §1.3).
 */
final class LoginRequest extends IdentityRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:120'],
        ];
    }

    public function email(): string
    {
        return $this->validatedString('email');
    }

    public function password(): string
    {
        return $this->validatedString('password');
    }

    public function deviceName(): string
    {
        return $this->validatedString('device_name');
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseEmail();
    }

    protected function attributeKeys(): array
    {
        return ['email' => 'email', 'password' => 'password', 'device_name' => 'device_name'];
    }
}
