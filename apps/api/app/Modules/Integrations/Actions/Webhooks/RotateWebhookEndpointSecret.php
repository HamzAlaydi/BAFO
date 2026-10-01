<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\Webhooks;

use App\Modules\Integrations\Data\IssuedWebhookEndpoint;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Services\Webhooks\WebhookSigner;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * `POST …/webhook-endpoints/{endpoint}/rotate-secret` (ARCHITECTURE §14.5): the new secret signs
 * every attempt from now on (no dual-secret overlap in the MVP) and is returned once.
 */
final class RotateWebhookEndpointSecret
{
    public function handle(WebhookEndpoint $endpoint, Actor $actor): IssuedWebhookEndpoint
    {
        return DB::transaction(static function () use ($endpoint, $actor): IssuedWebhookEndpoint {
            $endpoint = WebhookEndpoint::query()->lockForUpdate()->findOrFail($endpoint->id);
            $secret = WebhookSigner::generateSecret();

            $endpoint->fill(['secret' => $secret])->save();

            AuditLogger::log('webhook_endpoint.secret_rotated', $endpoint, actor: $actor);

            return new IssuedWebhookEndpoint($endpoint, $secret);
        });
    }
}
