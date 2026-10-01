<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\ImportJob;
use Illuminate\Auth\Access\Response;

/**
 * Import jobs (API.md §1.9): `integrations.manage`, own organization only.
 */
final class ImportJobPolicy
{
    use ChecksOrganization;

    public function view(User $user, ImportJob $job): Response
    {
        return self::owned($user, $job->organization_id, Permission::IntegrationsManage);
    }
}
