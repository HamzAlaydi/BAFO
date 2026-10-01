<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Region (API.md §2.4): `{"id", "code", "name"}`, the name in the request locale. Other modules
 * may embed it (the organization's region).
 *
 * @mixin Region
 */
final class RegionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->translated('name'),
        ];
    }
}
