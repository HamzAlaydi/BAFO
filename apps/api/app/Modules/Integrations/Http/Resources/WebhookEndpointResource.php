<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Resources;

use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `WebhookEndpoint` (API.md §2.12). Create and rotate responses add `secret` once.
 *
 * @mixin WebhookEndpoint
 */
final class WebhookEndpointResource extends JsonResource
{
    private ?string $secret = null;

    public function withSecret(string $secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'url' => $this->url,
            'description' => $this->description,
            'event_types' => $this->event_types,
            'status' => $this->status->value,
            'disabled_reason' => $this->disabled_reason?->value,
            'failing_since' => Iso::format($this->failing_since),
            'last_success_at' => Iso::format($this->last_success_at),
            'last_failure_at' => Iso::format($this->last_failure_at),
            'created_at' => Iso::format($this->created_at),
            ...($this->secret !== null ? ['secret' => $this->secret] : []),
        ];
    }
}
