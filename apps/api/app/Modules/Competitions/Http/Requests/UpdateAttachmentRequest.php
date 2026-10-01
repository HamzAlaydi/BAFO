<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `PATCH /competitions/{competition}/attachments/{attachment}` (API.md §1.4): `title`, `sort_order`.
 */
final class UpdateAttachmentRequest extends FormRequest
{
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
            'title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:32767'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (['title', 'sort_order'] as $field) {
            $value = __('competitions.attributes.'.$field);
            $attributes[$field] = is_string($value) ? $value : $field;
        }

        return $attributes;
    }

    /**
     * @return array{title?: string|null, sort_order?: int}
     */
    public function changes(): array
    {
        $data = $this->validated();
        $changes = [];

        if (array_key_exists('title', $data)) {
            $changes['title'] = is_string($data['title']) ? $data['title'] : null;
        }

        if (array_key_exists('sort_order', $data)) {
            $changes['sort_order'] = (int) $data['sort_order'];
        }

        return $changes;
    }
}
