<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Console\Commands;

use App\Modules\Integrations\Enums\DeliveryStatus;
use App\Modules\Integrations\Jobs\DeliverWebhook;
use App\Modules\Integrations\Jobs\DispatchWebhookEvent;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

/**
 * `integrations:dispatch-webhooks` (every minute, ARCHITECTURE §12): the safety net of the
 * outbox. Undispatched events older than 30 s get DispatchWebhookEvent; `pending` deliveries whose
 * `next_attempt_at` is due get DeliverWebhook. Both jobs are idempotent.
 */
final class DispatchWebhooksCommand extends Command
{
    protected $signature = 'integrations:dispatch-webhooks';

    protected $description = 'Dispatch stranded webhook events and due webhook deliveries';

    public function handle(): int
    {
        $now = Date::now();
        $events = 0;
        $deliveries = 0;

        WebhookEvent::query()
            ->whereNull('dispatched_at')
            ->where('created_at', '<=', $now->subSeconds((int) config('bafo.integrations.webhooks.dispatch_grace_seconds')))
            ->orderBy('id')
            ->chunkById(500, static function ($chunk) use (&$events): void {
                foreach ($chunk as $event) {
                    DispatchWebhookEvent::dispatch($event->id);
                    $events++;
                }
            });

        WebhookDelivery::query()
            ->where('status', DeliveryStatus::Pending)
            ->where('next_attempt_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(500, static function ($chunk) use (&$deliveries): void {
                foreach ($chunk as $delivery) {
                    DeliverWebhook::dispatch($delivery->id);
                    $deliveries++;
                }
            });

        $this->components->info("Dispatched {$events} events and {$deliveries} deliveries.");

        return self::SUCCESS;
    }
}
