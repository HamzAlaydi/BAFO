<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\Vendor;
use Illuminate\Auth\Access\Response;

/**
 * The vendor directory (API.md §1.9): every issuer member with `competitions.create`, own
 * organization only.
 */
final class VendorPolicy
{
    use ChecksOrganization;

    public function viewAny(User $user): Response
    {
        return self::permitted($user, Permission::CompetitionsCreate);
    }

    public function create(User $user): Response
    {
        return self::permitted($user, Permission::CompetitionsCreate);
    }

    public function view(User $user, Vendor $vendor): Response
    {
        return self::owned($user, $vendor->organization_id, Permission::CompetitionsCreate);
    }

    public function update(User $user, Vendor $vendor): Response
    {
        return self::owned($user, $vendor->organization_id, Permission::CompetitionsCreate);
    }

    public function delete(User $user, Vendor $vendor): Response
    {
        return self::owned($user, $vendor->organization_id, Permission::CompetitionsCreate);
    }
}
