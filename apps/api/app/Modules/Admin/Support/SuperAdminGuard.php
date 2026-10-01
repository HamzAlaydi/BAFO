<?php

declare(strict_types=1);

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\Admin;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;

/**
 * Keeps the panel manageable: an admin never demotes, deactivates or deletes their own account,
 * and at least one active super admin always remains. Call it inside the transaction, after
 * locking the admins rows.
 */
final class SuperAdminGuard
{
    public static function assertNotSelf(Admin $target, Actor $actor): void
    {
        if ($actor->adminId === $target->id) {
            throw new ApiException('cannot_modify_self', 'admin.errors.cannot_modify_self', 409);
        }
    }

    /**
     * @param  bool  $remains  whether the target is still an active super admin afterwards
     */
    public static function assertAnotherSuperAdminRemains(Admin $target, bool $remains): void
    {
        $wasActiveSuperAdmin = $target->is_active && $target->role === AdminRole::SuperAdmin;

        if (! $wasActiveSuperAdmin || $remains) {
            return;
        }

        // Locks every active super admin row (FOR UPDATE cannot be combined with count()).
        $activeSuperAdmins = Admin::query()
            ->where('role', AdminRole::SuperAdmin->value)
            ->where('is_active', true)
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        if (array_diff($activeSuperAdmins, [$target->id]) === []) {
            throw new ApiException('conflict', 'admin.errors.last_super_admin', 409);
        }
    }
}
