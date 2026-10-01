<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\OtpPurpose;
use Illuminate\Validation\Rule;

/**
 * `POST /auth/otp/check` (API.md §1.3): password reset codes only.
 */
final class CheckOtpRequest extends IdentityRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
            'purpose' => ['required', 'string', Rule::in([OtpPurpose::PasswordReset->value])],
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
        return ['email' => 'email', 'code' => 'code', 'purpose' => 'purpose'];
    }
}
