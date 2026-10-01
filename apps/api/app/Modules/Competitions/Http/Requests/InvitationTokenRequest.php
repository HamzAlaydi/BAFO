<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A request that carries an invitation token in its JSON body (never in the URL, D11):
 * lookup, decline by token and claim (API.md §1.5).
 */
abstract class InvitationTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function token(): string
    {
        return trim($this->string('token')->toString());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (['token', 'reason', 'code', 'website_url'] as $field) {
            $value = __('competitions.attributes.'.$field);
            $attributes[$field] = is_string($value) ? $value : $field;
        }

        return $attributes;
    }
}
