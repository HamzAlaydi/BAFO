<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Services\Entitlements;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The API client's own organization on the public API (API.md §3.2, `GET /organization`). Names
 * of lookups are `{"ar", "en"}`.
 *
 * @mixin Organization
 */
final class PublicOrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Organization $organization */
        $organization = $this->resource;
        $organization->loadMissing('region');
        $region = $organization->region;
        $subscription = app(Entitlements::class)->currentSubscription($organization);
        $subscription?->loadMissing('plan');

        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'legal_name_ar' => $this->legal_name_ar,
            'legal_name_en' => $this->legal_name_en,
            'cr_number' => $this->cr_number,
            'vat_number' => $this->vat_number,
            'national_address' => [
                'building_number' => $this->address_building_number,
                'street' => $this->address_street,
                'district' => $this->address_district,
                'postal_code' => $this->address_postal_code,
                'additional_number' => $this->address_additional_number,
                'short_address' => $this->address_short,
            ],
            'region' => [
                'code' => $region->code,
                'name' => ['ar' => $region->translated('name', 'ar'), 'en' => $region->translated('name', 'en')],
            ],
            'city' => $this->city,
            'website' => $this->website,
            'email' => $this->email,
            'phone' => $this->phone,
            'features' => [
                'auction_enabled' => $this->auction_enabled,
                'sponsorship_enabled' => $this->sponsorship_enabled,
            ],
            'subscription' => $subscription !== null ? [
                'plan_code' => $subscription->plan->code,
                'status' => $subscription->status->value,
                'ends_at' => Iso::format($subscription->ends_at),
            ] : null,
        ];
    }
}
