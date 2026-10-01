<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Database\Factories;

use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\WebhookDisabledReason;
use App\Modules\Integrations\Enums\WebhookEndpointStatus;
use App\Modules\Integrations\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active endpoint subscribed to every event type, with a `whsec_` secret.
 *
 * @extends Factory<WebhookEndpoint>
 */
final class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory()->apiEnabled(),
            'url' => 'https://erp.example.sa/webhooks/bafo/'.$this->faker->unique()->numerify('####'),
            'description' => 'استقبال أحداث بافو في نظام تخطيط الموارد',
            'event_types' => ['*'],
            'secret' => 'whsec_'.base64_encode(random_bytes(32)),
            'status' => WebhookEndpointStatus::Active,
            'created_by_user_id' => null,
            'created_by_api_client_id' => null,
        ];
    }

    /**
     * @param  list<string>  $eventTypes
     */
    public function eventTypes(array $eventTypes): self
    {
        return $this->state(['event_types' => $eventTypes]);
    }

    public function disabled(WebhookDisabledReason $reason = WebhookDisabledReason::Manual): self
    {
        return $this->state(['status' => WebhookEndpointStatus::Disabled, 'disabled_reason' => $reason]);
    }
}
