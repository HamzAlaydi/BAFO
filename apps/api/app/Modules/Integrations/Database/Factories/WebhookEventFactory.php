<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Database\Factories;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An undispatched `competition.published` outbox row for a competition of the organization.
 * The payload is a thin placeholder, not the full API.md §4.2 envelope.
 *
 * @extends Factory<WebhookEvent>
 */
final class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'type' => 'competition.published',
            'subject_type' => 'competition',
            'subject_id' => static fn (array $attributes): int => Competition::factory()
                ->create(['organization_id' => $attributes['organization_id']])
                ->id,
            'sequence' => 1,
            'payload' => static fn (array $attributes): array => [
                'type' => $attributes['type'],
                'data' => ['object' => ['type' => $attributes['subject_type']]],
            ],
            'occurred_at' => now(),
            'dispatched_at' => null,
        ];
    }

    public function dispatched(): self
    {
        return $this->state(fn (): array => ['dispatched_at' => now()]);
    }
}
