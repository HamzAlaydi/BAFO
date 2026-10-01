<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Category (API.md §2.4): `{"id", "code", "name", "is_other", "auction_allowed"}`, the name in
 * the request locale. Other modules may embed it (organization and competition categories).
 *
 * @mixin Category
 */
final class CategoryResource extends JsonResource
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
            'is_other' => $this->is_other,
            'auction_allowed' => $this->auction_allowed,
        ];
    }
}
