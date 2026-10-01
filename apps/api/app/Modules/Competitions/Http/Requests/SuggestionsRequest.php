<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `GET /competitions/{competition}/suggestions` query (API.md §1.4).
 */
final class SuggestionsRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'string', 'max:26'],
            'region_id' => ['nullable', 'string', 'max:26'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
