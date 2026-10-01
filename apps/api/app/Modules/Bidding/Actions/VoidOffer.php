<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Actions;

use App\Modules\Bidding\Events\OfferVoided;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\OfferVoid;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Bidding\Services\LiveStateManager;
use App\Modules\Bidding\Services\Ranking;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Clock\DbClock;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * A platform admin voids one offer (ARCHITECTURE §7.13), from the Filament panel. The ledger row
 * is never changed: an `offer_voids` row is added, the participant's standing is rebuilt from
 * the ledger minus voids, the competition is re-ranked and the live state follows.
 *
 * Allowed while the competition is not awarded, not_awarded or cancelled (revoke an award
 * before voiding the awarded offer).
 */
final readonly class VoidOffer
{
    private const array FINAL_STATUSES = [
        CompetitionStatus::Awarded,
        CompetitionStatus::NotAwarded,
        CompetitionStatus::Cancelled,
    ];

    public function __construct(
        private DbClock $clock,
        private Ranking $ranking,
        private LiveStateManager $liveStates,
    ) {}

    public function handle(Offer $offer, CloseReason $reason, ?string $note, Actor $actor): OfferVoid
    {
        if (! $actor->isAdmin() || $actor->adminId === null) {
            throw new ApiException('forbidden', status: 403);
        }

        if ($reason->kind !== CloseReasonKind::VoidOffer || ! $reason->is_active) {
            throw new ApiException('validation_failed', status: 422, errors: ['reason_id' => [__('bidding.validation.void_reason_invalid')]]);
        }

        $note = $note !== null && trim($note) !== '' ? trim($note) : null;

        if ($reason->requires_note && $note === null) {
            throw new ApiException('validation_failed', status: 422, errors: ['note' => [__('bidding.validation.note_required')]]);
        }

        return DB::transaction(function () use ($offer, $reason, $note, $actor): OfferVoid {
            $locked = Competition::query()->whereKey($offer->competition_id)->lockForUpdate()->firstOrFail();
            $now = $this->clock->now();

            if (in_array($locked->status, self::FINAL_STATUSES, true)) {
                throw new ApiException('invalid_state_transition', status: 409, details: ['status' => $locked->status->value]);
            }

            if (OfferVoid::query()->where('offer_id', $offer->id)->exists()) {
                // CONTRACT-GAP: §7.13 names no code for a second void of the same offer.
                throw new ApiException('invalid_state_transition', status: 409, details: ['status' => 'voided']);
            }

            $void = OfferVoid::query()->create([
                'offer_id' => $offer->id,
                'reason_id' => $reason->id,
                'note' => $note,
                'voided_by_admin_id' => $actor->adminId,
            ]);

            $this->rebuildStanding($offer);

            $this->ranking->recompute($locked, $now);

            $state = $this->liveStates->forLocked($locked);
            $this->liveStates->syncFromStandings($locked, $state);
            $this->liveStates->recountOffers($locked, $state);
            $state->version++;
            $state->save();

            AuditLogger::log('offer.voided', $offer, meta: [
                'competition_id' => $locked->public_id,
                'seq' => $offer->seq,
                'reason' => $reason->code,
                'note' => $note,
            ], actor: $actor, organizationId: $locked->organization_id);

            event(new OfferVoided($void, $offer, $locked));

            return $void;
        });
    }

    /**
     * The standing from the ledger minus voids: current = the latest non-voided offer, first =
     * the earliest, `offers_count`. A voided BAFO offer frees the participant's BAFO slot.
     */
    private function rebuildStanding(Offer $voided): void
    {
        $standing = ParticipantStanding::query()->find($voided->participant_id);

        if ($standing === null) {
            return;
        }

        $offers = Offer::query()
            ->where('participant_id', $voided->participant_id)
            ->whereDoesntHave('void')
            ->orderBy('seq')
            ->get();

        $first = $offers->first();
        $current = $offers->last();

        $standing->fill([
            'current_offer_id' => $current?->id,
            'current_amount_minor' => $current?->amount_minor,
            'current_rank_key' => $current?->rank_key,
            'current_at' => $current?->accepted_at,
            'current_seq' => $current?->seq,
            'first_amount_minor' => $first?->amount_minor,
            'offers_count' => $offers->count(),
            'last_offer_at' => $current?->accepted_at,
        ]);

        if ($standing->bafo_offer_id === $voided->id) {
            $standing->bafo_offer_id = null;
        }

        $standing->save();
    }
}
