<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Database\Factories;

use App\Modules\Identity\Database\Factories\Support\SaudiData;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\VendorSource;
use App\Modules\Integrations\Enums\VendorStatus;
use App\Modules\Integrations\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active vendor of a new issuer organization, added from the dashboard.
 *
 * @extends Factory<Vendor>
 */
final class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = SaudiData::company($this->faker);
        $city = SaudiData::city($this->faker);

        return [
            'organization_id' => Organization::factory(),
            'name' => $company['name'],
            'name_en' => $company['legal_name_en'],
            'cr_number' => SaudiData::crNumber($this->faker, $city['cr_prefix']),
            'vat_number' => SaudiData::vatNumber($this->faker),
            'email' => 'sales'.$this->faker->unique()->numerify('####').'@'.$company['domain'],
            'contact_name' => SaudiData::personName(),
            'phone' => SaudiData::mobile($this->faker),
            'region_id' => null,
            'city' => $city['city'],
            'status' => VendorStatus::Active,
            'linked_organization_id' => null,
            'source' => VendorSource::Web,
            'notes' => null,
        ];
    }

    public function blocked(): self
    {
        return $this->state(['status' => VendorStatus::Blocked]);
    }

    public function archived(): self
    {
        return $this->state(['status' => VendorStatus::Archived]);
    }

    /**
     * Matched to a BAFO organization (same e-mail or CR).
     */
    public function linkedTo(Organization $organization): self
    {
        return $this->state([
            'linked_organization_id' => $organization->id,
            'email' => $organization->email,
            'cr_number' => $organization->cr_number,
        ]);
    }

    public function source(VendorSource $source): self
    {
        return $this->state(['source' => $source]);
    }
}
