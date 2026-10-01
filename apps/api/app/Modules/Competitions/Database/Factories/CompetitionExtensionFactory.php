<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Database\Factories;

use App\Modules\Competitions\Enums\ExtensionKind;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionExtension;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A 30-minute manual extension of a live competition. The row only: the competition's
 * `effective_close_at` is not moved.
 *
 * @extends Factory<CompetitionExtension>
 */
final class CompetitionExtensionFactory extends Factory
{
    protected $model = CompetitionExtension::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory()->live(),
            'kind' => ExtensionKind::Manual,
            'previous_close_at' => static fn (array $attributes): CarbonImmutable => Competition::query()
                ->findOrFail($attributes['competition_id'])
                ->effective_close_at ?? CarbonImmutable::now()->addHour(),
            'new_close_at' => static fn (array $attributes): CarbonImmutable => CarbonImmutable::instance($attributes['previous_close_at'])
                ->addMinutes(30),
            'triggered_by_offer_id' => null,
            'actor_user_id' => null,
            'actor_admin_id' => null,
            'reason' => 'تمديد لإتاحة وقت إضافي للمتنافسين لاستكمال عروضهم',
        ];
    }

    /**
     * An anti-sniping extension (§7.7) triggered by an offer id.
     */
    public function auto(int $triggeredByOfferId): self
    {
        return $this->state([
            'kind' => ExtensionKind::Auto,
            'triggered_by_offer_id' => $triggeredByOfferId,
            'new_close_at' => static fn (array $resolved): CarbonImmutable => CarbonImmutable::instance($resolved['previous_close_at'])->addSeconds(180),
            'reason' => null,
        ]);
    }
}
