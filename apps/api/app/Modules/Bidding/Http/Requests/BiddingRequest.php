<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base of the Bidding FormRequests: authorization happens in the controllers and the Actions
 * (so failures render the 403/404 envelope consistently); attribute names come from
 * `bidding.attributes.*`.
 */
abstract class BiddingRequest extends FormRequest
{
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

        foreach (array_keys($this->rules()) as $field) {
            $key = 'bidding.attributes.'.str_replace(['.*.', '.*'], ['_', ''], (string) $field);
            $label = __($key);
            $attributes[(string) $field] = is_string($label) && $label !== $key ? $label : (string) $field;
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    protected function stringOrNull(string $key): ?string
    {
        $value = $this->validated($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
