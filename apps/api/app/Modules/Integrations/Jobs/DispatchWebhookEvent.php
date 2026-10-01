<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Jobs;

use App\Modules\Integrations\Enums\WebhookEndpointStatus;
use App\Modules\Integrations\Enums\WebhookEventType;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Fans an outbox row out to its endpoints (ARCHITECTURE §14.5 "Dispatch"): one
 * `webhook_deliveries` row per active endpoint subscribed to the type (for `webhook.test`, the
 * tested endpoint only), then `dispatched_at`, then DeliverWebhook for each delivery.
 * Idempotent: an event already dispatched is left alone.
 */
final class DispatchWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $webhookEventId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $deliveryIds = DB::transaction(function (): array {
            $event = WebhookEvent::query()->lockForUpdate()->find($this->webhookEventId);

            if ($event === null || $event->dispatched_at !== null) {
                return [];
            }

            $now = Date::now();
            $ids = [];

            foreach ($this->endpointsFor($event) as $endpoint) {
                $delivery = WebhookDelivery::query()->firstOrCreate(
                    ['webhook_event_id' => $event->id, 'webhook_endpoint_id' => $endpoint->id],
                    ['next_attempt_at' => $now],
                );
                $ids[] = $delivery->id;
            }

            $event->forceFill(['dispatched_at' => $now])->save();

            return $ids;
        });

        foreach ($deliveryIds as $deliveryId) {
            DeliverWebhook::dispatch($deliveryId);
        }
    }

    /**
     * @return iterable<WebhookEndpoint>
     */
    private function endpointsFor(WebhookEvent $event): iterable
    {
        if ($event->type === WebhookEventType::WebhookTest->value) {
            // A test targets its endpoint whatever the subscription (even a disabled one: the
            // delivery then records why it was not sent).
            return WebhookEndpoint::query()
                ->where('organization_id', $event->organization_id)
                ->whereKey($event->subject_id)
                ->get();
        }

        return WebhookEndpoint::query()
            ->where('organization_id', $event->organization_id)
            ->where('status', WebhookEndpointStatus::Active)
            ->get()
            ->filter(static fn (WebhookEndpoint $endpoint): bool => $endpoint->listensTo($event->type));
    }
}
