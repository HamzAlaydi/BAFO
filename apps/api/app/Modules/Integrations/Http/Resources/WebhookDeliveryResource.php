<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Resources;

use App\Modules\Integrations\Models\WebhookDelivery;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `WebhookDelivery` (API.md §2.12).
 *
 * @mixin WebhookDelivery
 */
final class WebhookDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('event');
        $event = $this->event;

        return [
            'id' => $this->public_id,
            'event' => $event === null ? null : [
                'id' => $event->public_id,
                'type' => $event->type,
                'occurred_at' => Iso::format($event->occurred_at),
            ],
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'next_attempt_at' => Iso::format($this->next_attempt_at),
            'last_attempt_at' => Iso::format($this->last_attempt_at),
            'last_http_status' => $this->last_http_status,
            'last_error' => $this->last_error,
            'last_response_excerpt' => $this->last_response_excerpt,
            'last_duration_ms' => $this->last_duration_ms,
            'succeeded_at' => Iso::format($this->succeeded_at),
            'failed_at' => Iso::format($this->failed_at),
        ];
    }
}
