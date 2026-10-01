<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

/**
 * `POST /auth/password/forgot` (API.md §1.3).
 */
final class ForgotPasswordRequest extends IdentityRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function email(): string
    {
        return $this->validatedString('email');
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseEmail();
    }

    protected function attributeKeys(): array
    {
        return ['email' => 'email'];
    }
}
