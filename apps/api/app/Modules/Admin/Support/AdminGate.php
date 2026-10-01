<?php

declare(strict_types=1);

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Models\Admin;

/**
 * `Gate::before()` for platform admins. The module policies and the `perm` gate are written for
 * organization users (Identity `User`), so an admin never reaches them: every ability is denied
 * unless it concerns the Admin model itself, which AdminPolicy decides. The panel resources
 * authorize through PanelResourcePolicy instead of the Gate.
 */
final class AdminGate
{
    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function before(Admin $admin, string $ability, array $arguments): ?bool
    {
        $subject = $arguments[0] ?? null;

        if ($subject instanceof Admin || $subject === Admin::class) {
            return null;
        }

        return false;
    }
}
