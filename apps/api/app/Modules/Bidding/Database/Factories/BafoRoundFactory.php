<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Database\Factories;

use App\Modules\Bidding\Enums\BafoRoundStatus;
use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A running 60-minute BAFO round of a competition in `bafo_round`, started by an issuer member.
 *
 * @extends Factory<BafoRound>
 */
final class BafoRoundFactory extends Factory
{
    protected $model = BafoRound::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::now()->subMinutes(10);

        return [
            'competition_id' => Competition::factory()->inBafoRound(),
            'started_by_user_id' => static fn (array $attributes): int => User::factory()
                ->withMembership(Organization::query()->findOrFail(
                    Competition::query()->findOrFail($attributes['competition_id'])->organization_id,
                ))
                ->create()
                ->id,
            'status' => BafoRoundStatus::Running,
            'starts_at' => $startsAt,
            'cutoff_at' => $startsAt->addMinutes(60),
            'ended_at' => null,
            'shortlist_count' => 2,
        ];
    }

    public function ended(): self
    {
        return $this->state(fn (): array => [
            'status' => BafoRoundStatus::Ended,
            'starts_at' => CarbonImmutable::now()->subMinutes(90),
            'cutoff_at' => CarbonImmutable::now()->subMinutes(30),
            'ended_at' => CarbonImmutable::now()->subMinutes(30),
        ]);
    }
}
