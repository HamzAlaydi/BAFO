<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base of the Integrations FormRequests: authorization happens in middleware and controllers,
 * attribute names come from `integrations.attributes.*`.
 */
abstract class IntegrationsRequest extends FormRequest
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
            $name = explode('.', $field)[0];
            $attributes[$field] = self::trans('integrations.attributes.'.$name);
        }

        return $attributes;
    }

    /**
     * @return array<string, list<mixed>>
     */
    abstract public function rules(): array;

    protected static function trans(string $key): string
    {
        $value = __($key);

        return is_string($value) ? $value : $key;
    }

    protected function nullableString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
