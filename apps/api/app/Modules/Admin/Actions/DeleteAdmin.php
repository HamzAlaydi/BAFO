<?php

declare(strict_types=1);

namespace App\Modules\Admin\Actions;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Support\SuperAdminGuard;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * Removes a platform admin (§16 "Admins"). Not one's own account, and never the last active super
 * admin. The `*_admin_id` references elsewhere are plain ids, so history keeps the id; the
 * audit log keeps the name in `actor_label`. Audited as `admin.deleted`.
 */
final class DeleteAdmin
{
    public function handle(Admin $admin, Actor $actor): void
    {
        DB::transaction(static function () use ($admin, $actor): void {
            /** @var Admin $locked */
            $locked = Admin::query()->whereKey($admin->id)->lockForUpdate()->firstOrFail();

            SuperAdminGuard::assertNotSelf($locked, $actor);
            SuperAdminGuard::assertAnotherSuperAdminRemains($locked, false);

            AuditLogger::log('admin.deleted', $locked, meta: ['email' => $locked->email], actor: $actor);

            $locked->delete();
        });
    }
}
