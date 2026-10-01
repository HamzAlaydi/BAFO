<?php

declare(strict_types=1);

namespace App\Modules\Platform\Database\Factories;

use App\Modules\Platform\Enums\ContactStatus;
use App\Modules\Platform\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
final class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+9665'.fake()->numerify('########'),
            'company' => fake()->company(),
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'locale' => 'ar',
            'status' => ContactStatus::New,
            'ip' => fake()->ipv4(),
            'user_agent' => 'Mozilla/5.0',
        ];
    }
}
