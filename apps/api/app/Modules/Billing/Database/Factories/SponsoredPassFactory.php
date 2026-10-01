<?php

declare(strict_types=1);

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Enums\PassReleaseReason;
use App\Modules\Billing\Enums\PassSource;
use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Competitions\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A reserved admin-granted pass for a sent invitation of the sponsored competition (§6.3).
 *
 * @extends Factory<SponsoredPass>
 */
final class SponsoredPassFactory extends Factory
{
    protected $model = SponsoredPass::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sponsorship_id' => CompetitionSponsorship::factory()->funded(),
            'competition_id' => static fn (array $attributes): int => CompetitionSponsorship::query()
                ->findOrFail($attributes['sponsorship_id'])
                ->competition_id,
            'invitation_id' => static fn (array $attributes): int => Invitation::factory()
                ->sent()
                ->create(['competition_id' => $attributes['competition_id'], 'sponsored_requested' => true])
                ->id,
            'organization_id' => null,
            'payment_id' => null,
            'source' => PassSource::AdminGrant,
            'status' => PassStatus::Reserved,
            'release_reason' => null,
            'hold_expires_at' => null,
            'reserved_at' => now(),
        ];
    }

    /**
     * Held for a sponsorship checkout (30 minutes).
     */
    public function pending(): self
    {
        return $this->state(fn (): array => [
            'source' => PassSource::Purchase,
            'status' => PassStatus::Pending,
            'hold_expires_at' => now()->addMinutes(30),
            'reserved_at' => null,
        ]);
    }

    public function released(PassReleaseReason $reason = PassReleaseReason::Declined): self
    {
        return $this->state(fn (): array => [
            'status' => PassStatus::Released,
            'release_reason' => $reason,
            'released_at' => now(),
        ]);
    }
}
