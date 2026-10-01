<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\Webhooks;

use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * `DELETE …/webhook-endpoints/{endpoint}`: soft delete. Pending deliveries to it are not sent.
 */
final class DeleteWebhookEndpoint
{
    public function handle(WebhookEndpoint $endpoint, Actor $actor): void
    {
        DB::transaction(static function () use ($endpoint, $actor): void {
            $endpoint->delete();

            AuditLogger::log('webhook_endpoint.deleted', $endpoint, actor: $actor);
        });
    }
}
