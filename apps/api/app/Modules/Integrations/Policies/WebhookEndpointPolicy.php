<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\WebhookEndpoint;
use Illuminate\Auth\Access\Response;

/**
 * Webhook endpoints and their delivery log (API.md §1.9): `integrations.manage`, own
 * organization only.
 */
final class WebhookEndpointPolicy
{
    use ChecksOrganization;

    public function manage(User $user, WebhookEndpoint $endpoint): Response
    {
        return self::owned($user, $endpoint->organization_id, Permission::IntegrationsManage);
    }
}
