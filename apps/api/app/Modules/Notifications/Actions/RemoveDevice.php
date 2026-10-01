<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\DeviceToken;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * `DELETE /devices/{device}` (API.md §1.8), called by the apps on sign-out. Audited as
 * `device.removed`.
 */
final class RemoveDevice
{
    public function handle(User $user, DeviceToken $device): void
    {
        DB::transaction(static function () use ($user, $device): void {
            $device->delete();

            AuditLogger::log('device.removed', $user, meta: [
                'device_id' => $device->public_id,
                'platform' => $device->platform->value,
            ]);
        });
    }
}
