<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Jobs;

use App\Modules\Integrations\Services\Webhooks\WebhookDeliverer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One attempt of one webhook delivery (ARCHITECTURE §14.5), queue `webhooks`. Idempotent: a
 * delivery that is not pending or not yet due is left alone (WebhookDeliverer claims it first).
 * A failed attempt schedules the next one itself; `integrations:dispatch-webhooks` also sweeps
 * due deliveries every minute.
 */
final class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $webhookDeliveryId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(WebhookDeliverer $deliverer): void
    {
        $deliverer->deliver($this->webhookDeliveryId);
    }
}
