<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\OtpPurpose;
use Illuminate\Validation\Rule;

/**
 * `POST /auth/otp/verify` (API.md §1.3): e-mail verification only.
 */
final class VerifyOtpRequest extends IdentityRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
            'purpose' => ['sometimes', 'string', Rule::in([OtpPurpose::EmailVerification->value])],
            'device_name' => ['required', 'string', 'max:120'],
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
        return ['email' => 'email', 'code' => 'code', 'purpose' => 'purpose', 'device_name' => 'device_name'];
    }
}
