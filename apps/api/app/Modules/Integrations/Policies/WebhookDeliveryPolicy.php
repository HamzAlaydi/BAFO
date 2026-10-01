<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use Illuminate\Auth\Access\Response;

/**
 * Redelivery (API.md §1.9): the delivery's endpoint belongs to the user's organization.
 */
final class WebhookDeliveryPolicy
{
    use ChecksOrganization;

    public function manage(User $user, WebhookDelivery $delivery): Response
    {
        $organizationId = WebhookEndpoint::withTrashed()->whereKey($delivery->webhook_endpoint_id)->value('organization_id');

        return self::owned($user, is_numeric($organizationId) ? (int) $organizationId : null, Permission::IntegrationsManage);
    }
}
