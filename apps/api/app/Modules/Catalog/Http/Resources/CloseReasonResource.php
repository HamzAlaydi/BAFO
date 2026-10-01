<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\CloseReason;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CloseReason (API.md §2.4): `{"id", "code", "kind", "name", "requires_note"}`, the name in the
 * request locale.
 *
 * @mixin CloseReason
 */
final class CloseReasonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'kind' => $this->kind->value,
            'name' => $this->translated('name'),
            'requires_note' => $this->requires_note,
        ];
    }
}
