<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Database\Factories;

use App\Modules\Bidding\Models\OfferRejection;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Database\Factories\Support\SaudiData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An `offer_step_not_met` rejection of a participant's attempt (§7.4).
 *
 * @extends Factory<OfferRejection>
 */
final class OfferRejectionFactory extends Factory
{
    protected $model = OfferRejection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = CarbonImmutable::now();

        return [
            'participant_id' => Participant::factory(),
            'competition_id' => static fn (array $attributes): int => Participant::query()
                ->findOrFail($attributes['participant_id'])
                ->competition_id,
            'user_id' => static fn (array $attributes): int => Participant::query()
                ->findOrFail($attributes['participant_id'])
                ->joined_by_user_id,
            'amount_minor' => SaudiData::halalas($this->faker, 50_000, 95_000),
            'code' => 'offer_step_not_met',
            'idempotency_key' => (string) Str::uuid(),
            'stage' => 'live',
            'received_at' => $now,
            'db_time' => $now,
            'channel' => 'web',
            'ip' => $this->faker->ipv4(),
        ];
    }

    public function code(string $code): self
    {
        return $this->state(['code' => $code]);
    }
}
