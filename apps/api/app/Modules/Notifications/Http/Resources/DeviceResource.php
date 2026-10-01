<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Resources;

use App\Modules\Notifications\Models\DeviceToken;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `Device` (API.md §2.11). The push token itself is never returned.
 *
 * @property DeviceToken $resource
 */
final class DeviceResource extends JsonResource
{
    /**
     * @return array{id: string, platform: string, device_name: string|null, app_version: string|null, last_seen_at: string|null}
     */
    public function toArray(Request $request): array
    {
        $device = $this->resource;

        return [
            'id' => $device->public_id,
            'platform' => $device->platform->value,
            'device_name' => $device->device_name,
            'app_version' => $device->app_version,
            'last_seen_at' => Iso::format($device->last_seen_at),
        ];
    }
}
