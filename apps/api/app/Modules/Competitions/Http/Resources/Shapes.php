<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Resources;

use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Http\Resources\CloseReasonResource;
use App\Modules\Catalog\Http\Resources\PublicLookupResource;
use App\Modules\Catalog\Http\Resources\RegionResource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Catalog\Models\Region;
use App\Modules\Identity\Models\Organization;
use App\Support\Files\FileStorage;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The small shapes embedded in competition payloads: the lookups of API.md §2.4 through
 * Catalog's resources (app v1 names in the request locale, public v1 names `{"ar", "en"}`), and
 * OrganizationSummary (§2.2).
 */
final class Shapes
{
    /**
     * @return array<string, mixed>|null
     */
    public static function category(?Category $category, bool $public = false): ?array
    {
        return $category === null ? null : self::resolve($public ? new PublicLookupResource($category) : new CategoryResource($category));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function region(?Region $region, bool $public = false): ?array
    {
        return $region === null ? null : self::resolve($public ? new PublicLookupResource($region) : new RegionResource($region));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function closeReason(?CloseReason $reason, bool $public = false): ?array
    {
        return $reason === null ? null : self::resolve($public ? new PublicLookupResource($reason) : new CloseReasonResource($reason));
    }

    /**
     * OrganizationSummary: `{"id", "name", "logo_url", "verified"}`.
     *
     * @return array{id: string, name: string, logo_url: string|null, verified: bool}|null
     */
    public static function organization(?Organization $organization): ?array
    {
        if ($organization === null) {
            return null;
        }

        $logo = $organization->logo_file_id !== null ? $organization->logoFile : null;

        return [
            'id' => $organization->public_id,
            'name' => $organization->name,
            'logo_url' => $logo !== null ? app(FileStorage::class)->publicUrl($logo) : null,
            'verified' => $organization->verified_at !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolve(JsonResource $resource): array
    {
        /** @var array<string, mixed> $data */
        $data = $resource->resolve(request());

        return $data;
    }
}
