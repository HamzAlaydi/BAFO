<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Database\Factories;

use App\Modules\Bidding\Models\CompetitionLiveState;
use App\Modules\Competitions\Models\Competition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The empty live state of a live competition (no offers yet).
 *
 * @extends Factory<CompetitionLiveState>
 */
final class CompetitionLiveStateFactory extends Factory
{
    protected $model = CompetitionLiveState::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory()->live(),
            'version' => 0,
            'last_seq' => 0,
            'leader_participant_id' => null,
            'leader_offer_id' => null,
            'leader_amount_minor' => null,
            'accepted_offer_count' => 0,
            'participants_with_offers' => 0,
            'reserve_met' => null,
            'ledger_head_hash' => null,
        ];
    }
}
