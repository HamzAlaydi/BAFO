<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Enums\CloseReasonKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `GET /lookups/{type}` (app and public): `?kind=` filters the close reasons (API.md §1.2).
 */
final class ShowLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['sometimes', 'nullable', 'string', Rule::enum(CloseReasonKind::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $label = __('catalog.attributes.kind');

        return ['kind' => is_string($label) ? $label : 'kind'];
    }

    public function kind(): ?CloseReasonKind
    {
        $kind = $this->validated('kind');

        return is_string($kind) ? CloseReasonKind::from($kind) : null;
    }
}
