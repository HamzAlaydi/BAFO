<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /competitions/{competition}/comments` (API.md §1.4): `body` 1–2000, `parent_id` (a
 * top-level comment of the same competition; checked by the controller).
 */
final class StoreCommentRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:1', 'max:2000'],
            'parent_id' => ['nullable', 'string', 'max:26'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (['body', 'parent_id'] as $field) {
            $value = __('competitions.attributes.'.$field);
            $attributes[$field] = is_string($value) ? $value : $field;
        }

        return $attributes;
    }
}
