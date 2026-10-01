<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\Webhooks;

use App\Modules\Integrations\Enums\DeliveryStatus;
use App\Modules\Integrations\Jobs\DeliverWebhook;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * `POST …/webhook-deliveries/{delivery}/redeliver` (ARCHITECTURE §14.5, §6.6 `failed → pending`):
 * `pending` with `next_attempt_at = now`, attempts kept, then one attempt is queued. Only a failed
 * delivery can be redelivered (409 `invalid_state_transition` otherwise).
 */
final class RedeliverWebhook
{
    public function handle(WebhookDelivery $delivery, Actor $actor): WebhookDelivery
    {
        $delivery = DB::transaction(static function () use ($delivery, $actor): WebhookDelivery {
            $delivery = WebhookDelivery::query()->with('endpoint')->lockForUpdate()->findOrFail($delivery->id);

            if ($delivery->status !== DeliveryStatus::Failed) {
                throw new ApiException('invalid_state_transition', status: 409, details: [
                    'from' => $delivery->status->value,
                    'to' => DeliveryStatus::Pending->value,
                ]);
            }

            $delivery->fill([
                'status' => DeliveryStatus::Pending,
                'next_attempt_at' => Date::now(),
                'failed_at' => null,
            ])->save();

            AuditLogger::log('webhook_delivery.redelivered', $delivery->endpoint,
                meta: ['delivery_id' => $delivery->public_id], actor: $actor);

            return $delivery;
        });

        DeliverWebhook::dispatch($delivery->id)->afterCommit();

        return $delivery;
    }
}
