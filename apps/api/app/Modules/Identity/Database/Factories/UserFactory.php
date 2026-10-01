<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Database\Factories\Support\SaudiData;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * An active, verified user without a membership. Use `withMembership()` (or
 * `Membership::factory()`) to place the user in an organization. The password is `password`.
 *
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    private static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => SaudiData::personName(),
            'email' => 'user'.$this->faker->unique()->numerify('######').'@'.$this->faker->randomElement(['masdar.sa', 'ofoq.com.sa', 'rowad.sa', 'example.sa']),
            'phone' => SaudiData::mobile($this->faker),
            'password' => self::$password ??= Hash::make('password'),
            'email_verified_at' => now(),
            'locale' => 'ar',
            'status' => UserStatus::Active,
        ];
    }

    /**
     * Places the user in an organization (a new one unless given).
     */
    public function withMembership(?Organization $organization = null, OrgRole $role = OrgRole::Member): self
    {
        $membership = Membership::factory()->role($role);

        return $this->has(
            $organization === null ? $membership : $membership->for($organization),
            'membership',
        );
    }

    /**
     * Registered but the OTP is not verified yet (§13.9).
     */
    public function unverified(): self
    {
        return $this->state([
            'email_verified_at' => null,
            'status' => UserStatus::PendingVerification,
        ]);
    }

    /**
     * A pending team invitation: no password yet (§13.10).
     */
    public function invited(): self
    {
        return $this->state(['password' => null, 'email_verified_at' => null]);
    }

    public function english(): self
    {
        return $this->state(['locale' => 'en']);
    }
}
