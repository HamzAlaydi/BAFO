<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Database\Factories;

use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The SAP supplier number of a vendor of the same organization. Attach it to another subject
 * with `->for($model, 'refable')` (plus a matching `organization_id`).
 *
 * @extends Factory<ExternalRef>
 */
final class ExternalRefFactory extends Factory
{
    protected $model = ExternalRef::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'refable_type' => 'vendor',
            'refable_id' => static fn (array $attributes): int => Vendor::factory()
                ->create(['organization_id' => $attributes['organization_id']])
                ->id,
            'system' => 'sap_s4',
            'type' => 'supplier',
            'value' => $this->faker->unique()->numerify('10######'),
            'number' => null,
            'url' => null,
            'created_by_api_client_id' => null,
        ];
    }
}
