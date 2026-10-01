<?php

declare(strict_types=1);

namespace App\Modules\Admin\Policies;

use App\Modules\Admin\Models\Admin;
use Illuminate\Auth\Access\Response;

/**
 * Who may use a panel resource built on another module's model (ARCHITECTURE §8.7, §16).
 *
 * Those models' own policies are written for organization users, so the panel never sends an
 * admin through them: every active admin may view, only the abilities a resource declares as
 * writable are allowed, and the super-admin-only resources (admins, plans, prices, coupons,
 * settings) are closed to operators. State changes beyond create/update/delete are the module
 * Actions the resource calls.
 */
final class PanelResourcePolicy
{
    /**
     * @param  list<string>  $writable  abilities besides viewing, e.g. ['create', 'update', 'delete']
     */
    public function inspect(mixed $user, string $ability, bool $superAdminOnly, array $writable): Response
    {
        if (! $user instanceof Admin || ! $user->is_active) {
            return Response::deny();
        }

        if ($superAdminOnly && ! $user->isSuperAdmin()) {
            return Response::deny();
        }

        if (in_array($ability, ['viewAny', 'view'], true) || in_array($ability, $writable, true)) {
            return Response::allow();
        }

        return Response::deny();
    }
}
