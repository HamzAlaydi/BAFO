<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Data\DeviceRegistration;
use App\Modules\Notifications\Models\DeviceToken;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * `POST /devices` (API.md §1.8): upsert by token. A known token is re-assigned to the caller
 * (a device changes hands on sign-in) and `last_seen_at` is refreshed; the insert is an
 * `INSERT … ON CONFLICT (token)`, so concurrent registrations of one token cannot collide.
 *
 * A new registration, or one taken over from another user, is audited as `device.registered`
 * (§4.5); a refresh by the same user is not. The token never reaches the audit log.
 */
final class RegisterDevice
{
    public function handle(User $user, DeviceRegistration $registration): DeviceToken
    {
        return DB::transaction(static function () use ($user, $registration): DeviceToken {
            $previousOwner = DeviceToken::query()
                ->where('token', $registration->token)
                ->lockForUpdate()
                ->value('user_id');

            DeviceToken::query()->upsert(
                [[
                    'public_id' => DeviceToken::newPublicId(),
                    'user_id' => $user->getKey(),
                    'token' => $registration->token,
                    'platform' => $registration->platform->value,
                    'device_name' => $registration->deviceName,
                    'app_version' => $registration->appVersion,
                    'locale' => $registration->locale,
                    'last_seen_at' => Date::now(),
                ]],
                uniqueBy: ['token'],
                update: ['user_id', 'platform', 'device_name', 'app_version', 'locale', 'last_seen_at'],
            );

            $device = DeviceToken::query()->where('token', $registration->token)->firstOrFail();

            if ($previousOwner === null || (int) $previousOwner !== (int) $user->getKey()) {
                AuditLogger::log('device.registered', $user, meta: [
                    'device_id' => $device->public_id,
                    'platform' => $device->platform->value,
                    'reassigned' => $previousOwner !== null,
                ]);
            }

            return $device;
        });
    }
}
