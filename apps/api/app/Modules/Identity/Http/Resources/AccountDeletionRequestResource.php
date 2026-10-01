<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\AccountDeletionRequest;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * AccountDeletionRequest (API.md §1.3): `{id, scope, status, scheduled_for, created_at}`.
 *
 * @mixin AccountDeletionRequest
 */
final class AccountDeletionRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'scope' => $this->scope->value,
            'status' => $this->status->value,
            'scheduled_for' => Iso::format($this->scheduled_for),
            'created_at' => Iso::format($this->created_at),
        ];
    }
}
