<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Http\Resources\RegionResource;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Http\Requests\OrganizationProfileFields;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Services\Entitlements;
use App\Modules\Platform\Http\Resources\FileResource;
use App\Support\Files\FileStorage;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Organization, own view (API.md §2.2): `GET /organization` and the `organization` key of `Me`.
 *
 * @mixin Organization
 */
final class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Organization $organization */
        $organization = $this->resource;
        $organization->loadMissing(['region', 'categories', 'logoFile', 'profileFile']);

        $profile = $organization->profileFile;
        $categories = $organization->categories->sortBy('sort_order')->values();

        return [
            'id' => $this->public_id,
            'name' => self::displayName($organization),
            'legal_name_ar' => $this->legal_name_ar,
            'legal_name_en' => $this->legal_name_en,
            'cr_number' => $this->cr_number,
            'vat_registered' => $this->vat_registered,
            'vat_number' => $this->vat_number,
            'region' => (new RegionResource($organization->region))->resolve($request),
            'city' => $this->city,
            'national_address' => [
                'building_number' => $this->address_building_number,
                'street' => $this->address_street,
                'district' => $this->address_district,
                'postal_code' => $this->address_postal_code,
                'additional_number' => $this->address_additional_number,
                'short_address' => $this->address_short,
            ],
            'website' => $this->website,
            'email' => $this->email,
            'phone' => $this->phone,
            'logo_url' => self::logoUrl($organization),
            'profile_document' => $profile !== null ? (new FileResource($profile))->resolve($request) : null,
            'categories' => CategoryResource::collection($categories)->resolve($request),
            'visible_in_suggestions' => $this->visible_in_suggestions,
            'status' => $this->status->value,
            'verified' => $this->isVerified(),
            'features' => [
                'api_enabled' => $this->api_enabled,
                'auction_enabled' => $this->auction_enabled,
                'sponsorship_enabled' => $this->sponsorship_enabled,
            ],
            'billing_profile_complete' => $this->isBillingProfileComplete(),
            'billing_profile_missing' => self::missingBillingFields($organization),
            'trial_available' => app(Entitlements::class)->trialAvailable($organization),
            'created_at' => Iso::format($this->created_at),
        ];
    }

    /**
     * A deleted organization renders as `identity.deleted_organization`.
     */
    public static function displayName(Organization $organization): string
    {
        $deleted = __('identity.deleted_organization');

        return $organization->status === OrganizationStatus::Deleted && is_string($deleted) ? $deleted : $organization->name;
    }

    public static function logoUrl(Organization $organization): ?string
    {
        $logo = $organization->logoFile;

        return $logo !== null ? app(FileStorage::class)->publicUrl($logo) : null;
    }

    /**
     * The missing billing-profile fields as API field paths (`national_address.building_number`,
     * not the column name), API.md §2.2.
     *
     * @return list<string>
     */
    public static function missingBillingFields(Organization $organization): array
    {
        $paths = array_flip(OrganizationProfileFields::ADDRESS_COLUMNS);

        return array_map(
            static fn (string $column): string => isset($paths[$column]) ? 'national_address.'.$paths[$column] : $column,
            $organization->missingBillingProfileFields(),
        );
    }
}
