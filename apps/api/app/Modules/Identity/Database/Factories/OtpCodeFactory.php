<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An unconsumed e-mail verification code `123456` (the OTP_FAKE_CODE of the tests), valid for
 * 10 minutes.
 *
 * @extends Factory<OtpCode>
 */
final class OtpCodeFactory extends Factory
{
    protected $model = OtpCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => 'user'.$this->faker->unique()->numerify('######').'@example.sa',
            'user_id' => null,
            'purpose' => OtpPurpose::EmailVerification,
            'code_hash' => OtpCode::hashCode('123456'),
            'context' => null,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
            'ip' => $this->faker->ipv4(),
        ];
    }

    public function forUser(User $user): self
    {
        return $this->state(['user_id' => $user->id, 'email' => $user->email]);
    }

    public function code(string $code): self
    {
        return $this->state(['code_hash' => OtpCode::hashCode($code)]);
    }

    public function purpose(OtpPurpose $purpose): self
    {
        return $this->state(['purpose' => $purpose]);
    }

    public function expired(): self
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }

    public function consumed(): self
    {
        return $this->state(fn (): array => ['consumed_at' => now()]);
    }
}
