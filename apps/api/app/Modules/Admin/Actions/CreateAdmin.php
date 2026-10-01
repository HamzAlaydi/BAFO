<?php

declare(strict_types=1);

namespace App\Modules\Admin\Actions;

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\Admin;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * Adds a platform admin (§16 "Admins", super admins only). Audited as `admin.created`.
 */
final class CreateAdmin
{
    /**
     * @param  array{name: string, email: string, password: string, role: AdminRole|string, is_active?: bool}  $data
     */
    public function handle(array $data, Actor $actor): Admin
    {
        return DB::transaction(static function () use ($data, $actor): Admin {
            $admin = Admin::query()->create([
                'name' => trim($data['name']),
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $data['role'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            AuditLogger::log('admin.created', $admin, meta: [
                'email' => $admin->email,
                'role' => $admin->role->value,
            ], actor: $actor);

            return $admin;
        });
    }
}
