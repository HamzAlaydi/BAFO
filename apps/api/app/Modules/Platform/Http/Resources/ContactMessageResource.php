<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Resources;

use App\Modules\Platform\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Receipt of POST /contact: {"id": "01j…"} (API.md §1.1).
 *
 * @mixin ContactMessage
 */
final class ContactMessageResource extends JsonResource
{
    /**
     * @return array{id: string}
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->public_id];
    }
}
