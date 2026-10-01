<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Enums\DeletionScope;
use App\Modules\Identity\Enums\DeletionStatus;
use App\Modules\Identity\Models\AccountDeletionRequest;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending member deletion, executed 14 days later (§13.8).
 *
 * @extends Factory<AccountDeletionRequest>
 */
final class AccountDeletionRequestFactory extends Factory
{
    protected $model = AccountDeletionRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'scope' => DeletionScope::User,
            'reason' => 'لم نعد نحتاج إلى الحساب',
            'status' => DeletionStatus::Pending,
            'scheduled_for' => now()->addDays(14),
        ];
    }

    public function organizationScope(): self
    {
        return $this->state(['scope' => DeletionScope::Organization]);
    }

    public function cancelled(): self
    {
        return $this->state(fn (): array => ['status' => DeletionStatus::Cancelled, 'cancelled_at' => now()]);
    }

    public function completed(): self
    {
        return $this->state(fn (): array => ['status' => DeletionStatus::Completed, 'completed_at' => now()]);
    }
}
