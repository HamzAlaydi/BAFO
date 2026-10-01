<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Listeners;

use App\Modules\Identity\Enums\DeletionScope;
use App\Modules\Integrations\Actions\ApiClients\RevokeApiClient;
use App\Modules\Integrations\Enums\ApiClientStatus;
use App\Modules\Integrations\Enums\WebhookDisabledReason;
use App\Modules\Integrations\Enums\WebhookEndpointStatus;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\DB;

/**
 * `Identity\AccountDeleted` with scope `organization` (ARCHITECTURE §10, §13.8): revokes the
 * organization's API clients and keys and disables its webhook endpoints. User-scope deletions
 * are ignored.
 */
final class RevokeOrganizationApiAccess implements ShouldHandleEventsAfterCommit, ShouldQueueAfterCommit
{
    public string $queue = 'default';

    public function __construct(private readonly RevokeApiClient $revokeClient) {}

    public function handle(object $event): void
    {
        $organizationId = EventProperty::int($event, 'organizationId');
        $scope = EventProperty::optional($event, 'scope', DeletionScope::class);

        if ($organizationId === null || $scope !== DeletionScope::Organization) {
            return;
        }

        $actor = Actor::system();

        ApiClient::query()
            ->where('organization_id', $organizationId)
            ->where('status', '!=', ApiClientStatus::Revoked)
            ->each(fn (ApiClient $client): ApiClient => $this->revokeClient->handle($client, $actor));

        DB::transaction(static function () use ($organizationId, $actor): void {
            WebhookEndpoint::query()
                ->where('organization_id', $organizationId)
                ->where('status', WebhookEndpointStatus::Active)
                ->each(static function (WebhookEndpoint $endpoint) use ($actor): void {
                    $endpoint->fill(['status' => WebhookEndpointStatus::Disabled, 'disabled_reason' => WebhookDisabledReason::Manual])->save();
                    AuditLogger::log('webhook_endpoint.disabled', $endpoint, meta: ['reason' => 'account_deleted'], actor: $actor);
                });
        });
    }
}
