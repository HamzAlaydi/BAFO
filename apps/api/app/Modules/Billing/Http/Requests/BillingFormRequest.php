<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base of the Billing form requests: authorization happens in the route (`can:`) or the
 * controller (CONVENTIONS §2.3), and attribute names come from `billing.attributes.*`.
 */
abstract class BillingFormRequest extends FormRequest
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
            $key = 'billing.attributes.'.str_replace('.*.', '.', (string) $field);
            $label = __($key);

            if (is_string($label) && $label !== $key) {
                $attributes[(string) $field] = $label;
            }
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

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
