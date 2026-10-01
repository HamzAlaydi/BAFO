<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An active member (no award or purchase rights) of a new organization.
 *
 * @extends Factory<Membership>
 */
final class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'role' => OrgRole::Member,
            'can_award' => false,
            'can_purchase' => false,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ];
    }

    /**
     * The role with the §8.1 defaults: owner and admin get `can_award` and `can_purchase`.
     */
    public function role(OrgRole $role): self
    {
        $privileged = $role !== OrgRole::Member;

        return $this->state([
            'role' => $role,
            'can_award' => $privileged,
            'can_purchase' => $privileged,
        ]);
    }

    public function owner(): self
    {
        return $this->role(OrgRole::Owner);
    }

    public function admin(): self
    {
        return $this->role(OrgRole::Admin);
    }

    /**
     * A pending team invitation (+7 days). Pass the plain token to use it in a test; the row
     * stores only its sha256 (§5.3).
     */
    public function invited(?string $plainToken = null): self
    {
        return $this->state(fn (): array => [
            'status' => MembershipStatus::Invited,
            'invite_token_hash' => hash('sha256', $plainToken ?? Str::random(40)),
            'invite_expires_at' => now()->addDays(7),
            'joined_at' => null,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(['status' => MembershipStatus::Inactive]);
    }
}
