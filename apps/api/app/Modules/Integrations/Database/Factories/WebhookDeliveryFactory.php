<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Database\Factories;

use App\Modules\Integrations\Enums\DeliveryStatus;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending first delivery of an event to an endpoint of the same organization.
 *
 * @extends Factory<WebhookDelivery>
 */
final class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_event_id' => WebhookEvent::factory(),
            'webhook_endpoint_id' => static fn (array $attributes): int => WebhookEndpoint::factory()
                ->create(['organization_id' => WebhookEvent::query()->findOrFail($attributes['webhook_event_id'])->organization_id])
                ->id,
            'status' => DeliveryStatus::Pending,
            'attempts' => 0,
            'next_attempt_at' => now(),
        ];
    }

    public function succeeded(): self
    {
        return $this->state(fn (): array => [
            'status' => DeliveryStatus::Succeeded,
            'attempts' => 1,
            'next_attempt_at' => null,
            'last_attempt_at' => now(),
            'last_http_status' => 200,
            'last_duration_ms' => 184,
            'succeeded_at' => now(),
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn (): array => [
            'status' => DeliveryStatus::Failed,
            'attempts' => 8,
            'next_attempt_at' => null,
            'last_attempt_at' => now(),
            'last_http_status' => 500,
            'last_error' => 'HTTP 500',
            'failed_at' => now(),
        ]);
    }
}
