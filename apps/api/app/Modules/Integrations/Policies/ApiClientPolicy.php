<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\ApiClient;
use Illuminate\Auth\Access\Response;

/**
 * API clients and their keys (API.md §1.9): `integrations.manage`, own organization only. The
 * `api_enabled` gate is the `integrations.access:api` middleware.
 */
final class ApiClientPolicy
{
    use ChecksOrganization;

    public function manage(User $user, ApiClient $client): Response
    {
        return self::owned($user, $client->organization_id, Permission::IntegrationsManage);
    }
}
