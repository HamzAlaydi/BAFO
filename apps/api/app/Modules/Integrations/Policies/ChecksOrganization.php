<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Tenancy first, then the permission: an object of another organization is a 404 (its existence
 * is never revealed, CONVENTIONS §2.3); a missing permission in the own organization is a 403.
 */
trait ChecksOrganization
{
    private static function owned(User $user, ?int $organizationId, Permission $permission): Response
    {
        $userOrganizationId = $user->membership?->organization_id;

        if ($organizationId === null || $userOrganizationId !== $organizationId) {
            return Response::denyAsNotFound();
        }

        return $user->hasPermission($permission) ? Response::allow() : Response::deny();
    }

    private static function permitted(User $user, Permission $permission): Response
    {
        return $user->hasPermission($permission) ? Response::allow() : Response::deny();
    }
}
