<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Catalog\Models\Region;
use App\Modules\Identity\Database\Factories\Support\SaudiData;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active, VAT-registered Saudi company with a complete billing profile.
 *
 * @extends Factory<Organization>
 */
final class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = SaudiData::company($this->faker);
        $city = SaudiData::city($this->faker);

        return [
            'name' => $company['name'],
            'legal_name_ar' => $company['legal_name_ar'],
            'legal_name_en' => $company['legal_name_en'],
            'cr_number' => SaudiData::crNumber($this->faker, $city['cr_prefix']),
            'vat_registered' => true,
            'vat_number' => SaudiData::vatNumber($this->faker),
            'region_id' => Region::factory(),
            'city' => $city['city'],
            ...SaudiData::nationalAddress($this->faker),
            'website' => 'https://www.'.$company['domain'],
            'email' => 'info'.$this->faker->unique()->numerify('####').'@'.$company['domain'],
            'phone' => SaudiData::mobile($this->faker),
            'visible_in_suggestions' => true,
            'status' => OrganizationStatus::Active,
            'api_enabled' => false,
            'auction_enabled' => false,
            'sponsorship_enabled' => false,
        ];
    }

    /**
     * Creates the owner membership (and its user) with the organization.
     */
    public function withOwner(): self
    {
        return $this->has(Membership::factory()->owner(), 'memberships');
    }

    public function verified(): self
    {
        return $this->state(fn (): array => ['verified_at' => now()]);
    }

    public function suspended(): self
    {
        return $this->state(fn (): array => [
            'status' => OrganizationStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => 'مخالفة شروط الاستخدام',
        ]);
    }

    public function notVatRegistered(): self
    {
        return $this->state(['vat_registered' => false, 'vat_number' => null]);
    }

    /**
     * Missing legal and address data: checkout returns `billing_profile_incomplete`.
     */
    public function incompleteBillingProfile(): self
    {
        return $this->state([
            'legal_name_ar' => null,
            'address_building_number' => null,
            'address_street' => null,
            'address_district' => null,
            'address_postal_code' => null,
        ]);
    }

    public function apiEnabled(): self
    {
        return $this->state(['api_enabled' => true]);
    }

    public function auctionEnabled(): self
    {
        return $this->state(['auction_enabled' => true]);
    }

    public function sponsorshipEnabled(): self
    {
        return $this->state(['sponsorship_enabled' => true]);
    }
}
