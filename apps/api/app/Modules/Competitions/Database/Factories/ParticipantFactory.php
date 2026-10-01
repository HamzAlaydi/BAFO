<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Database\Factories;

use App\Modules\Billing\Enums\EntitlementSource;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An organization that joined a live competition on its own plan, with a consistent joined
 * invitation, a member user who joined, and a random free alias (1–99, then 100–999).
 *
 * @extends Factory<Participant>
 */
final class ParticipantFactory extends Factory
{
    protected $model = Participant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory()->live(),
            'organization_id' => Organization::factory(),
            'invitation_id' => static fn (array $attributes): int => Invitation::factory()->joined()->create([
                'competition_id' => $attributes['competition_id'],
                'organization_id' => $attributes['organization_id'],
                'email' => Organization::query()->findOrFail($attributes['organization_id'])->email,
            ])->id,
            'alias_no' => fn (array $attributes): int => $this->freeAlias((int) $attributes['competition_id']),
            'entitlement_source' => EntitlementSource::Plan,
            'terms_version' => '2026-10-01',
            'terms_accepted_at' => now(),
            'terms_ip' => $this->faker->ipv4(),
            'joined_by_user_id' => static fn (array $attributes): int => User::factory()
                ->withMembership(Organization::query()->findOrFail($attributes['organization_id']))
                ->create()
                ->id,
        ];
    }

    public function sponsored(): self
    {
        return $this->state(['entitlement_source' => EntitlementSource::SponsoredPass]);
    }

    /**
     * A random alias not used in the competition: 1–99 first, then 100–999 (§5.5).
     */
    private function freeAlias(int $competitionId): int
    {
        /** @var list<int> $used */
        $used = Participant::query()->where('competition_id', $competitionId)->pluck('alias_no')->all();
        $free = array_diff(range(1, 99), $used) ?: array_diff(range(100, 999), $used);

        return (int) $this->faker->randomElement($free);
    }
}
