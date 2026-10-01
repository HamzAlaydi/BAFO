<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Notifications\Services\IntegrationsNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `webhook.endpoint_disabled` on `App\Modules\Integrations\Events\WebhookEndpointDisabled` (ARCHITECTURE §10, §11.3).
 */
final class NotifyWebhookEndpointDisabled extends QueuedNotificationListener
{
    public function __construct(private readonly IntegrationsNotifier $notifier) {}

    /**
     * @param  object  $event  WebhookEndpointDisabled
     */
    public function handle(object $event): void
    {
        $this->notifier->webhookEndpointDisabled(EventPayload::instance($event, 'endpoint', WebhookEndpoint::class));
    }
}
