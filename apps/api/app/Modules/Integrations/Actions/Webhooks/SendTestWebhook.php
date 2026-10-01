<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\Webhooks;

use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Models\WebhookEvent;
use App\Modules\Integrations\Services\Webhooks\WebhookEmitter;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * `POST …/webhook-endpoints/{endpoint}/test` (ARCHITECTURE §14.5): a `webhook.test` event for
 * that endpoint only, whatever its subscription. Delivered asynchronously.
 */
final readonly class SendTestWebhook
{
    public function __construct(private WebhookEmitter $emitter) {}

    public function handle(WebhookEndpoint $endpoint, Actor $actor): WebhookEvent
    {
        return DB::transaction(function () use ($endpoint, $actor): WebhookEvent {
            $event = $this->emitter->emitTest($endpoint);

            AuditLogger::log('webhook_endpoint.tested', $endpoint, meta: ['event_id' => $event->public_id], actor: $actor);

            return $event;
        });
    }
}
