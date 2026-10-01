<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Database\Factories;

use App\Modules\Bidding\Enums\OfferStage;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Competitions\Enums\Format;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Database\Factories\Support\SaudiData;
use App\Support\Auth\Channel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The next row of a competition's offer ledger, submitted by a participant (a new participant
 * of a new live tender by default).
 *
 * Unless given, `seq` (gapless: last + 1), `prev_hash` (the previous row's hash), `rank_key`
 * (§7.1) and `hash` (§5.6 formula) are computed from the ledger when the model is made, so
 * consecutive `create()` calls build a valid hash chain. The factory writes only the offer row:
 * standings and the live state are not touched.
 *
 * @extends Factory<Offer>
 */
final class OfferFactory extends Factory
{
    protected $model = Offer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $acceptedAt = CarbonImmutable::now();

        return [
            'participant_id' => Participant::factory(),
            'competition_id' => static fn (array $attributes): int => Participant::query()
                ->findOrFail($attributes['participant_id'])
                ->competition_id,
            'organization_id' => static fn (array $attributes): int => Participant::query()
                ->findOrFail($attributes['participant_id'])
                ->organization_id,
            'submitted_by_user_id' => static fn (array $attributes): int => Participant::query()
                ->findOrFail($attributes['participant_id'])
                ->joined_by_user_id,
            'stage' => static fn (array $attributes): OfferStage => Competition::query()
                ->findOrFail($attributes['competition_id'])
                ->format === Format::Sealed ? OfferStage::Sealed : OfferStage::Live,
            // SAR 50,000–95,000 on whole riyals: below the default tender ceiling.
            'amount_minor' => SaudiData::halalas($this->faker, 50_000, 95_000),
            'accepted_at' => $acceptedAt,
            'idempotency_key' => (string) Str::uuid(),
            'channel' => Channel::Web,
            'ip' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'outlier_confirmed' => false,
        ];
    }

    public function stage(OfferStage $stage): self
    {
        return $this->state(['stage' => $stage]);
    }

    public function amount(int $amountMinor): self
    {
        return $this->state(['amount_minor' => $amountMinor]);
    }

    /**
     * Chains the ledger columns of every made offer, in order, before the other "after making"
     * callbacks. A batch (`count(n)`) is made before any row is stored, so the previous row of
     * the same competition in the batch is carried along instead of being read back.
     *
     * @param  Collection<int, Model>  $instances
     */
    protected function callAfterMaking(Collection $instances)
    {
        /** @var array<int, array{seq: int, hash: string}> $heads */
        $heads = [];

        foreach ($instances as $offer) {
            if ($offer instanceof Offer) {
                $heads[$offer->competition_id] = $this->chain($offer, $heads[$offer->competition_id] ?? null);
            }
        }

        parent::callAfterMaking($instances);
    }

    /**
     * Fills `seq`, `prev_hash`, `rank_key`, `hash` and `created_at` where they were not given.
     *
     * @param  array{seq: int, hash: string}|null  $previous  the previous row of this batch
     * @return array{seq: int, hash: string}
     */
    private function chain(Offer $offer, ?array $previous): array
    {
        $competition = Competition::query()->findOrFail($offer->competition_id);
        $participant = Participant::query()->findOrFail($offer->participant_id);

        if ($offer->getAttribute('seq') === null) {
            $offer->seq = $previous !== null
                ? $previous['seq'] + 1
                : (int) Offer::query()->where('competition_id', $competition->id)->max('seq') + 1;
        }

        if ($offer->getAttribute('prev_hash') === null && $offer->seq > 1) {
            $offer->prev_hash = $previous !== null && $previous['seq'] === $offer->seq - 1
                ? $previous['hash']
                : Offer::query()->where('competition_id', $competition->id)->where('seq', $offer->seq - 1)->value('hash');
        }

        if ($offer->getAttribute('rank_key') === null) {
            $offer->rank_key = Offer::rankKeyFor($competition->direction->sign(), $offer->amount_minor);
        }

        if ($offer->getAttribute('hash') === null) {
            $offer->hash = Offer::hashFor(
                $offer->prev_hash,
                $competition->public_id,
                $offer->seq,
                $participant->public_id,
                $offer->amount_minor,
                $offer->stage,
                $offer->accepted_at,
            );
        }

        if ($offer->getAttribute('created_at') === null) {
            $offer->created_at = $offer->accepted_at;
        }

        return ['seq' => $offer->seq, 'hash' => $offer->hash];
    }
}
