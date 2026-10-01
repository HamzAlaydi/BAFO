<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Requests;

use App\Modules\Integrations\Enums\ApiScope;
use Illuminate\Validation\Rule;

/**
 * `POST /integrations/api-clients` (API.md §1.9): name, description, scopes ⊆ ARCHITECTURE §14.4.
 */
final class StoreApiClientRequest extends IntegrationsRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', 'distinct', Rule::enum(ApiScope::class)],
        ];
    }

    /**
     * @return array{name: string, description: string|null, scopes: list<string>}
     */
    public function clientData(): array
    {
        return [
            'name' => trim((string) $this->validated('name')),
            'description' => $this->nullableString('description'),
            'scopes' => self::orderedScopes($this->validated('scopes')),
        ];
    }

    /**
     * The requested scopes in catalogue order.
     *
     * @return list<string>
     */
    public static function orderedScopes(mixed $scopes): array
    {
        $scopes = is_array($scopes) ? $scopes : [];

        return array_values(array_filter(
            array_map(static fn (ApiScope $scope): string => $scope->value, ApiScope::cases()),
            static fn (string $scope): bool => in_array($scope, $scopes, true),
        ));
    }
}
