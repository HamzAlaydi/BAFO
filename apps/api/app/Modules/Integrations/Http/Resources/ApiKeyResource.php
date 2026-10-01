<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Resources;

use App\Modules\Integrations\Models\ApiKey;
use App\Modules\Integrations\Services\ApiKeyGenerator;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `ApiKey` (API.md §2.12). The create response adds `key` (the plain key) once.
 *
 * @mixin ApiKey
 */
final class ApiKeyResource extends JsonResource
{
    private ?string $plainKey = null;

    public function withPlainKey(string $plainKey): self
    {
        $this->plainKey = $plainKey;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'prefix' => $this->prefix,
            'masked' => ApiKeyGenerator::mask($this->resource),
            'expires_at' => Iso::format($this->expires_at),
            'revoked_at' => Iso::format($this->revoked_at),
            'last_used_at' => Iso::format($this->last_used_at),
            'created_at' => Iso::format($this->created_at),
            ...($this->plainKey !== null ? ['key' => $this->plainKey] : []),
        ];
    }
}
