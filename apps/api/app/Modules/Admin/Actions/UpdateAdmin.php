<?php

declare(strict_types=1);

namespace App\Modules\Admin\Actions;

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Support\SuperAdminGuard;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * Edits a platform admin (§16 "Admins"): name, e-mail, role, active flag and, when given, a new
 * password. An admin cannot demote or deactivate their own account (409 `cannot_modify_self`),
 * and the last active super admin stays one (409 `conflict`). Audited as `admin.updated`.
 */
final class UpdateAdmin
{
    /**
     * @param  array{name?: string, email?: string, password?: string|null, role?: AdminRole|string, is_active?: bool}  $data
     */
    public function handle(Admin $admin, array $data, Actor $actor): Admin
    {
        return DB::transaction(static function () use ($admin, $data, $actor): Admin {
            /** @var Admin $locked */
            $locked = Admin::query()->whereKey($admin->id)->lockForUpdate()->firstOrFail();

            $role = isset($data['role']) ? ($data['role'] instanceof AdminRole ? $data['role'] : AdminRole::from($data['role'])) : $locked->role;
            $active = $data['is_active'] ?? $locked->is_active;

            if ($role !== $locked->role || $active !== $locked->is_active) {
                SuperAdminGuard::assertNotSelf($locked, $actor);
                SuperAdminGuard::assertAnotherSuperAdminRemains($locked, $active && $role === AdminRole::SuperAdmin);
            }

            $attributes = ['role' => $role, 'is_active' => $active];

            foreach (['name', 'email'] as $field) {
                if (isset($data[$field])) {
                    $attributes[$field] = trim($data[$field]);
                }
            }

            if (isset($data['password']) && $data['password'] !== '') {
                $attributes['password'] = $data['password'];
            }

            $locked->fill($attributes)->save();

            $changes = AuditLogger::diff($locked);

            if ($changes !== []) {
                AuditLogger::log('admin.updated', $locked, $changes, actor: $actor);
            }

            return $locked;
        });
    }
}
