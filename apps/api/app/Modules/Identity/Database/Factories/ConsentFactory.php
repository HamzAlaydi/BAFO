<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Enums\LegalDocumentCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Acceptance of the terms, version `2026-10-01` (the seeded placeholder legal documents, §17).
 *
 * @extends Factory<Consent>
 */
final class ConsentFactory extends Factory
{
    protected $model = Consent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'document_code' => LegalDocumentCode::Terms,
            'document_version' => '2026-10-01',
            'locale' => 'ar',
            'accepted_at' => now(),
            'ip' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }
}
