<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\ExportJob;
use Illuminate\Auth\Access\Response;

/**
 * Export jobs (API.md §1.9): `integrations.manage`, own organization only.
 */
final class ExportJobPolicy
{
    use ChecksOrganization;

    public function view(User $user, ExportJob $job): Response
    {
        return self::owned($user, $job->organization_id, Permission::IntegrationsManage);
    }
}
