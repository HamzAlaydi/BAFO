<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /contact (API.md §1.1). `website_url` is the honeypot: it must be absent or empty.
 */
final class StoreContactMessageRequest extends FormRequest
{
    public const string PHONE_PATTERN = '/^\+9665\d{8}$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:'.self::PHONE_PATTERN],
            'company' => ['nullable', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'website_url' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => $this->trans('platform.validation.phone'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (['name', 'email', 'phone', 'company', 'subject', 'message', 'website_url'] as $field) {
            $attributes[$field] = $this->trans('platform.attributes.'.$field);
        }

        return $attributes;
    }

    /**
     * @return array{name: string, email: string, phone: string|null, company: string|null, subject: string, message: string}
     */
    public function contactData(): array
    {
        /** @var array{name: string, email: string, phone?: string|null, company?: string|null, subject: string, message: string} $data */
        $data = $this->validated();

        return [
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'company' => isset($data['company']) && trim($data['company']) !== '' ? trim($data['company']) : null,
            'subject' => trim($data['subject']),
            'message' => trim($data['message']),
        ];
    }

    private function trans(string $key): string
    {
        $value = __($key);

        return is_string($value) ? $value : $key;
    }
}
