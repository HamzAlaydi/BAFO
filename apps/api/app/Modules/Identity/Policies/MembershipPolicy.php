<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Team management (ARCHITECTURE §8.1, §13.10): `team.manage` (403 `forbidden` without it), and
 * only memberships of the user's own organization (404 otherwise: existence is not revealed).
 */
final class MembershipPolicy
{
    public function viewAny(User $user): Response
    {
        return self::canManageTeam($user);
    }

    public function create(User $user): Response
    {
        return self::canManageTeam($user);
    }

    public function update(User $user, Membership $membership): Response
    {
        return self::canManage($user, $membership);
    }

    public function delete(User $user, Membership $membership): Response
    {
        return self::canManage($user, $membership);
    }

    public function resendInvitation(User $user, Membership $membership): Response
    {
        return self::canManage($user, $membership);
    }

    private static function canManage(User $user, Membership $membership): Response
    {
        $allowed = self::canManageTeam($user);

        if ($allowed->denied()) {
            return $allowed;
        }

        return $membership->organization_id === $user->membership?->organization_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    private static function canManageTeam(User $user): Response
    {
        return $user->hasPermission(Permission::TeamManage) ? Response::allow() : Response::deny();
    }
}
