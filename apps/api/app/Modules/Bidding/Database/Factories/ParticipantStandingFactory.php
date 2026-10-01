<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Database\Factories;

use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Competitions\Models\Participant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The standing of a participant without offers. `withOffer()` points it at an offer.
 *
 * @extends Factory<ParticipantStanding>
 */
final class ParticipantStandingFactory extends Factory
{
    protected $model = ParticipantStanding::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'participant_id' => Participant::factory(),
            'competition_id' => static fn (array $attributes): int => Participant::query()
                ->findOrFail($attributes['participant_id'])
                ->competition_id,
            'current_offer_id' => null,
            'offers_count' => 0,
            'rank' => null,
            'is_leader' => false,
            'bafo_shortlisted' => false,
        ];
    }

    /**
     * The standing after one offer (the participant's only one), ranked first.
     */
    public function withOffer(Offer $offer): self
    {
        return $this->state([
            'participant_id' => $offer->participant_id,
            'competition_id' => $offer->competition_id,
            'current_offer_id' => $offer->id,
            'current_amount_minor' => $offer->amount_minor,
            'current_rank_key' => $offer->rank_key,
            'current_at' => $offer->accepted_at,
            'current_seq' => $offer->seq,
            'first_amount_minor' => $offer->amount_minor,
            'offers_count' => 1,
            'rank' => 1,
            'is_leader' => true,
            'last_offer_at' => $offer->accepted_at,
        ]);
    }
}
