<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * `PATCH /me` (API.md §1.3): name, phone and locale, each optional.
 */
final class UpdateMeRequest extends IdentityRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:'.self::PHONE_PATTERN],
            'locale' => ['sometimes', 'required', 'string', Rule::in(['ar', 'en'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['phone.regex' => self::text('identity.validation.phone')];
    }

    /**
     * @return array{name?: string, phone?: string|null, locale?: string}
     */
    public function profile(): array
    {
        /** @var array{name?: string, phone?: string|null, locale?: string} $data */
        $data = $this->validated();

        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        return $data;
    }

    protected function attributeKeys(): array
    {
        return ['name' => 'name', 'phone' => 'phone', 'locale' => 'locale'];
    }
}
