<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /invitations/{invitation}/join`: `accept_terms`. Anything but true is the business error
 * 422 `terms_not_accepted` (ARCHITECTURE §13.11), raised by JoinCompetition.
 */
final class JoinCompetitionRequest extends FormRequest
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
            'accept_terms' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $value = __('competitions.attributes.accept_terms');

        return ['accept_terms' => is_string($value) ? $value : 'accept_terms'];
    }

    public function acceptsTerms(): bool
    {
        return $this->boolean('accept_terms');
    }
}
