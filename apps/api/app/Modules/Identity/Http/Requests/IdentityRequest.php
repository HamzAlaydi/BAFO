<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Base of the Identity FormRequests: authorization happens in controllers and policies
 * (CONVENTIONS §2.3); attribute names and messages come from `lang/*\/identity.php`.
 */
abstract class IdentityRequest extends FormRequest
{
    /**
     * Saudi mobile in E.164 (API.md §0.6).
     */
    public const string PHONE_PATTERN = '/^\+9665\d{8}$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->attributeKeys() as $field => $key) {
            $attributes[$field] = self::text('identity.attributes.'.$key);
        }

        return $attributes;
    }

    /**
     * The password rule of ARCHITECTURE §13.9: ≥ 8 characters with lower and upper case, a digit
     * and a symbol. Messages come from the validation lang files.
     */
    protected static function passwordRule(): Password
    {
        return Password::min(8)->mixedCase()->numbers()->symbols();
    }

    /**
     * Field path => key under `identity.attributes`.
     *
     * @return array<string, string>
     */
    abstract protected function attributeKeys(): array;

    protected static function text(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }

    /**
     * Lowercases and trims the e-mail input (e-mail columns store lowercase, §5.0).
     */
    protected function normaliseEmail(string $field = 'email'): void
    {
        $email = $this->input($field);

        if (is_string($email)) {
            $this->merge([$field => mb_strtolower(trim($email))]);
        }
    }

    protected function validatedString(string $key): string
    {
        $value = $this->validated($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
