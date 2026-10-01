<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\Webhooks;

use App\Modules\Integrations\Enums\WebhookDisabledReason;
use App\Modules\Integrations\Enums\WebhookEndpointStatus;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Services\Webhooks\WebhookUrlGuard;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * `PATCH …/webhook-endpoints/{endpoint}`: url (SSRF-checked), event types, description and
 * status (§6.6 `active ↔ disabled`). Disabling by hand records `manual`; re-enabling clears
 * `disabled_reason` and `failing_since`.
 */
final readonly class UpdateWebhookEndpoint
{
    use ValidatesWebhookUrl;

    public function __construct(private WebhookUrlGuard $guard) {}

    /**
     * @param  array{url?: string, event_types?: list<string>, description?: string|null, status?: WebhookEndpointStatus}  $data
     */
    public function handle(WebhookEndpoint $endpoint, array $data, Actor $actor): WebhookEndpoint
    {
        if (isset($data['url']) && $data['url'] !== $endpoint->url) {
            $this->assertWebhookUrl($this->guard, $data['url']);
        }

        return DB::transaction(static function () use ($endpoint, $data, $actor): WebhookEndpoint {
            $endpoint = WebhookEndpoint::query()->lockForUpdate()->findOrFail($endpoint->id);
            $status = $data['status'] ?? null;
            unset($data['status']);

            $endpoint->fill($data);

            if ($status === WebhookEndpointStatus::Disabled && $endpoint->isActive()) {
                $endpoint->fill(['status' => WebhookEndpointStatus::Disabled, 'disabled_reason' => WebhookDisabledReason::Manual]);
            } elseif ($status === WebhookEndpointStatus::Active && ! $endpoint->isActive()) {
                $endpoint->fill(['status' => WebhookEndpointStatus::Active, 'disabled_reason' => null, 'failing_since' => null]);
            }

            $endpoint->save();

            $changes = AuditLogger::diff($endpoint, ['url', 'event_types', 'description', 'status', 'disabled_reason']);

            if ($changes !== []) {
                AuditLogger::log('webhook_endpoint.updated', $endpoint, $changes, actor: $actor);
            }

            return $endpoint;
        });
    }
}
