<?php

declare(strict_types=1);

namespace App\Modules\Admin\Actions;

use App\Modules\Admin\Models\Admin;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * "Reset MFA" (§16 "Admins"): removes the admin's authenticator secret and recovery codes, so
 * the next sign-in sets up app authentication again (when it is required, §8.7). Audited as
 * `admin.mfa_reset`.
 */
final class ResetAdminMfa
{
    public function handle(Admin $admin, Actor $actor): Admin
    {
        return DB::transaction(static function () use ($admin, $actor): Admin {
            /** @var Admin $locked */
            $locked = Admin::query()->whereKey($admin->id)->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'app_authentication_secret' => null,
                'app_authentication_recovery_codes' => null,
            ])->save();

            AuditLogger::log('admin.mfa_reset', $locked, actor: $actor);

            return $locked;
        });
    }
}
