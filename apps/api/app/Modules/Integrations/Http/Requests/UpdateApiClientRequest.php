<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Requests;

use App\Modules\Integrations\Enums\ApiScope;
use Illuminate\Validation\Rule;

/**
 * `PATCH /integrations/api-clients/{client}`: name, description, scopes.
 */
final class UpdateApiClientRequest extends IntegrationsRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'scopes' => ['sometimes', 'required', 'array', 'min:1'],
            'scopes.*' => ['string', 'distinct', Rule::enum(ApiScope::class)],
        ];
    }

    /**
     * @return array{name?: string, description?: string|null, scopes?: list<string>}
     */
    public function clientData(): array
    {
        $data = [];

        if ($this->has('name')) {
            $data['name'] = trim((string) $this->validated('name'));
        }

        if ($this->has('description')) {
            $data['description'] = $this->nullableString('description');
        }

        if ($this->has('scopes')) {
            $data['scopes'] = StoreApiClientRequest::orderedScopes($this->validated('scopes'));
        }

        return $data;
    }
}
