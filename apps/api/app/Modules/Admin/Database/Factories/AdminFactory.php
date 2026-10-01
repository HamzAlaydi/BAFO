<?php

declare(strict_types=1);

namespace App\Modules\Admin\Database\Factories;

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\Admin;
use App\Modules\Identity\Database\Factories\Support\SaudiData;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * An active operator without MFA. The password is `password`.
 *
 * @extends Factory<Admin>
 */
final class AdminFactory extends Factory
{
    protected $model = Admin::class;

    private static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => SaudiData::personName(),
            'email' => 'ops'.$this->faker->unique()->numerify('####').'@bafo.example',
            'password' => self::$password ??= Hash::make('password'),
            'role' => AdminRole::Operator,
            'is_active' => true,
        ];
    }

    public function superAdmin(): self
    {
        return $this->state(['role' => AdminRole::SuperAdmin]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
