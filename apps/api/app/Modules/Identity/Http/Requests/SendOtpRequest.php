<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\OtpPurpose;
use Illuminate\Validation\Rule;

/**
 * `POST /auth/otp/send` (API.md §1.3).
 */
final class SendOtpRequest extends IdentityRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'purpose' => ['required', 'string', Rule::in([OtpPurpose::EmailVerification->value, OtpPurpose::PasswordReset->value])],
        ];
    }

    public function email(): string
    {
        return $this->validatedString('email');
    }

    public function purpose(): OtpPurpose
    {
        return OtpPurpose::from($this->validatedString('purpose'));
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseEmail();
    }

    protected function attributeKeys(): array
    {
        return ['email' => 'email', 'purpose' => 'purpose'];
    }
}
