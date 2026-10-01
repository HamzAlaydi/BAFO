<?php

declare(strict_types=1);

namespace App\Modules\Admin\Policies;

use App\Modules\Admin\Models\Admin;

/**
 * Managing platform admins (ARCHITECTURE §8.7, §16 "Admins"): super admins only. An admin never
 * deletes their own account.
 */
final class AdminPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->manages($admin);
    }

    public function view(Admin $admin, Admin $target): bool
    {
        return $this->manages($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->manages($admin);
    }

    public function update(Admin $admin, Admin $target): bool
    {
        return $this->manages($admin);
    }

    public function delete(Admin $admin, Admin $target): bool
    {
        return $this->manages($admin) && $admin->id !== $target->id;
    }

    public function deleteAny(Admin $admin): bool
    {
        return false;
    }

    public function resetMfa(Admin $admin, Admin $target): bool
    {
        return $this->manages($admin) && $target->app_authentication_secret !== null;
    }

    private function manages(Admin $admin): bool
    {
        return $admin->is_active && $admin->isSuperAdmin();
    }
}
