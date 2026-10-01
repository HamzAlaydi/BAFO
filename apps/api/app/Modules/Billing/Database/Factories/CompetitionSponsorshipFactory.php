<?php

declare(strict_types=1);

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Enums\SponsorshipMode;
use App\Modules\Billing\Enums\SponsorshipStatus;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft "fees covered for all invitees" configuration of a draft competition by its issuer,
 * at the default pass price (SAR 200 excl. VAT, §15.3).
 *
 * @extends Factory<CompetitionSponsorship>
 */
final class CompetitionSponsorshipFactory extends Factory
{
    protected $model = CompetitionSponsorship::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory()->state([
                'organization_id' => Organization::factory()->sponsorshipEnabled(),
            ]),
            'organization_id' => static fn (array $attributes): int => Competition::query()
                ->findOrFail($attributes['competition_id'])
                ->organization_id,
            'mode' => SponsorshipMode::All,
            'max_passes' => null,
            'unit_price_minor' => 20_000,
            'vat_rate_bp' => 1500,
            'funded_passes' => 0,
            'status' => SponsorshipStatus::Draft,
            'configured_by_user_id' => static fn (array $attributes): int => User::factory()
                ->withMembership(Organization::query()->findOrFail($attributes['organization_id']))
                ->create()
                ->id,
        ];
    }

    public function selected(?int $maxPasses = null): self
    {
        return $this->state(['mode' => SponsorshipMode::Selected, 'max_passes' => $maxPasses]);
    }

    /**
     * Paid for (or granted) slots: the sponsorship is active.
     */
    public function funded(int $passes = 3): self
    {
        return $this->state(['funded_passes' => $passes, 'status' => SponsorshipStatus::Active]);
    }
}
