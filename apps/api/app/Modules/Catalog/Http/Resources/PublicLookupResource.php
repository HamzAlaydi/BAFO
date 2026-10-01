<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Catalog\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lookup item of the public API (API.md §3.2): `{"id", "code", "name": {"ar", "en"}}` plus the
 * type fields (categories: `is_other`, `auction_allowed`; close reasons: `kind`, `requires_note`).
 *
 * @property Region|Category|CloseReason $resource
 */
final class PublicLookupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        $data = [
            'id' => $item->public_id,
            'code' => $item->code,
            'name' => ['ar' => $item->translated('name', 'ar'), 'en' => $item->translated('name', 'en')],
        ];

        if ($item instanceof Category) {
            $data['is_other'] = $item->is_other;
            $data['auction_allowed'] = $item->auction_allowed;
        }

        if ($item instanceof CloseReason) {
            $data['kind'] = $item->kind->value;
            $data['requires_note'] = $item->requires_note;
        }

        return $data;
    }
}
