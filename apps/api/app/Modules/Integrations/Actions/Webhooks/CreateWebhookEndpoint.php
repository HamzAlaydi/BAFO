<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\Webhooks;

use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Data\IssuedWebhookEndpoint;
use App\Modules\Integrations\Enums\WebhookEndpointStatus;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Services\Webhooks\WebhookSigner;
use App\Modules\Integrations\Services\Webhooks\WebhookUrlGuard;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * `POST …/webhook-endpoints` (app and public, ARCHITECTURE §14.5): SSRF-checked URL, event types,
 * and a new `whsec_` secret returned once.
 */
final readonly class CreateWebhookEndpoint
{
    use ValidatesWebhookUrl;

    public function __construct(private WebhookUrlGuard $guard) {}

    /**
     * @param  array{url: string, event_types: list<string>, description: string|null}  $data
     */
    public function handle(Organization $organization, array $data, Actor $actor): IssuedWebhookEndpoint
    {
        $this->assertWebhookUrl($this->guard, $data['url']);

        return DB::transaction(static function () use ($organization, $data, $actor): IssuedWebhookEndpoint {
            $secret = WebhookSigner::generateSecret();

            $endpoint = WebhookEndpoint::query()->create([
                'organization_id' => $organization->id,
                'url' => $data['url'],
                'description' => $data['description'],
                'event_types' => $data['event_types'],
                'secret' => $secret,
                'status' => WebhookEndpointStatus::Active,
                'created_by_user_id' => $actor->userId,
                'created_by_api_client_id' => $actor->apiClientId,
            ]);

            AuditLogger::log('webhook_endpoint.created', $endpoint,
                meta: ['url' => $endpoint->url, 'event_types' => $endpoint->event_types], actor: $actor);

            return new IssuedWebhookEndpoint($endpoint, $secret);
        });
    }
}
