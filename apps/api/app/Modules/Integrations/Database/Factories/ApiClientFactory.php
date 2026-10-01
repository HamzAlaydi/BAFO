<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Database\Factories;

use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\ApiClientStatus;
use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Models\ApiClient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active client of an API-enabled organization with the dashboard default scopes (§14.4).
 * No Passport client: `oauth_client_id` stays null unless a test creates one.
 *
 * @extends Factory<ApiClient>
 */
final class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory()->apiEnabled(),
            'name' => $this->faker->randomElement(['SAP S/4HANA', 'Oracle Fusion', 'Microsoft Dynamics 365', 'Odoo']),
            'description' => 'تكامل نظام تخطيط الموارد مع بافو',
            'scopes' => array_map(static fn (ApiScope $scope): string => $scope->value, ApiScope::defaults()),
            'status' => ApiClientStatus::Active,
            'oauth_client_id' => null,
            'created_by_user_id' => null,
        ];
    }

    /**
     * @param  list<ApiScope>  $scopes
     */
    public function scopes(array $scopes): self
    {
        return $this->state(['scopes' => array_map(static fn (ApiScope $scope): string => $scope->value, $scopes)]);
    }

    public function suspended(): self
    {
        return $this->state(['status' => ApiClientStatus::Suspended]);
    }

    public function revoked(): self
    {
        return $this->state(fn (): array => ['status' => ApiClientStatus::Revoked, 'revoked_at' => now()]);
    }
}
