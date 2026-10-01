<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Resources;

use App\Modules\Catalog\Models\Category;
use App\Modules\Integrations\Models\Vendor;
use App\Modules\Integrations\Services\ExternalRefs;
use App\Support\Files\File;
use App\Support\Files\FileStorage;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `Vendor` (API.md §2.12). App v1 renders lookup names in the request locale; the public API
 * (§3.3) renders `region` as `{"code", "name": {"ar", "en"}}` and `categories` as
 * `[{"code", "name"}]`.
 *
 * CONTRACT-GAP: the embedded Region, Category and OrganizationSummary shapes (§2.4, §2.2) are
 * rendered here until the Catalog and Identity resources exist; the shapes are the contract's.
 *
 * @mixin Vendor
 */
final class VendorResource extends JsonResource
{
    public const array RELATIONS = ['region', 'categories', 'linkedOrganization.logoFile', 'externalRefs'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $public = str_starts_with((string) $request->route()?->getName(), 'public.v1.');
        $this->resource->loadMissing(self::RELATIONS);
        $region = $this->region;
        $linked = $this->linkedOrganization;

        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'name_en' => $this->name_en,
            'cr_number' => $this->cr_number,
            'vat_number' => $this->vat_number,
            'email' => $this->email,
            'contact_name' => $this->contact_name,
            'phone' => $this->phone,
            'region' => $region === null ? null : ($public
                ? ['code' => $region->code, 'name' => $region->name]
                : ['id' => $region->public_id, 'code' => $region->code, 'name' => $region->translated('name')]),
            'city' => $this->city,
            'categories' => $this->categories->sortBy('sort_order')->values()->map(static fn (Category $category): array => $public
                ? ['code' => $category->code, 'name' => $category->name]
                : [
                    'id' => $category->public_id,
                    'code' => $category->code,
                    'name' => $category->translated('name'),
                    'is_other' => $category->is_other,
                    'auction_allowed' => $category->auction_allowed,
                ])->all(),
            'status' => $this->status->value,
            'linked_organization' => $linked === null ? null : [
                'id' => $linked->public_id,
                'name' => $linked->name,
                'logo_url' => $linked->logoFile instanceof File ? app(FileStorage::class)->publicUrl($linked->logoFile) : null,
                'verified' => $linked->isVerified(),
            ],
            'source' => $this->source->value,
            'notes' => $this->notes,
            'external_refs' => ExternalRefs::present($this->externalRefs),
            'created_at' => Iso::format($this->created_at),
            'updated_at' => Iso::format($this->updated_at),
        ];
    }
}
