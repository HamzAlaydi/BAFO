<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * OrganizationSummary (API.md §2.2), embedded by other modules: `{"id", "name", "logo_url",
 * "verified"}`.
 *
 * @mixin Organization
 */
final class OrganizationSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => OrganizationResource::displayName($this->resource),
            'logo_url' => OrganizationResource::logoUrl($this->resource),
            'verified' => $this->isVerified(),
        ];
    }
}
