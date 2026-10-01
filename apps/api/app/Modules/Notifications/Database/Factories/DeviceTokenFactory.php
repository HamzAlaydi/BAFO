<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Enums\DevicePlatform;
use App\Modules\Notifications\Models\DeviceToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An Android device registered for push (a fake FCM token).
 *
 * @extends Factory<DeviceToken>
 */
final class DeviceTokenFactory extends Factory
{
    protected $model = DeviceToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => Str::random(22).':APA91b'.Str::random(134),
            'platform' => DevicePlatform::Android,
            'device_name' => $this->faker->randomElement(['Samsung Galaxy S24', 'Google Pixel 8', 'Huawei P60']),
            'app_version' => '1.0.0',
            'locale' => 'ar',
            'last_seen_at' => now(),
        ];
    }

    public function ios(): self
    {
        return $this->state(['platform' => DevicePlatform::Ios, 'device_name' => 'iPhone 15 Pro']);
    }
}
