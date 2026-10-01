<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Resources;

use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ApiKey;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `ApiClient` (API.md §2.12): `client_id` equals `id`. Create and rotate responses add
 * `client_secret` once.
 *
 * @mixin ApiClient
 */
final class ApiClientResource extends JsonResource
{
    public const array RELATIONS = ['keys', 'createdBy'];

    private ?string $clientSecret = null;

    public function withClientSecret(string $clientSecret): self
    {
        $this->clientSecret = $clientSecret;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(self::RELATIONS);
        $creator = $this->createdBy;

        return [
            'id' => $this->public_id,
            'client_id' => $this->public_id,
            'name' => $this->name,
            'description' => $this->description,
            'scopes' => $this->scopes,
            'status' => $this->status->value,
            'keys' => $this->keys->sortByDesc('id')->values()
                ->map(static fn (ApiKey $key): array => (new ApiKeyResource($key))->resolve($request))->all(),
            'last_used_at' => Iso::format($this->last_used_at),
            'created_by' => $creator === null ? null : ['id' => $creator->public_id, 'name' => $creator->name],
            'created_at' => Iso::format($this->created_at),
            ...($this->clientSecret !== null ? ['client_secret' => $this->clientSecret] : []),
        ];
    }
}
