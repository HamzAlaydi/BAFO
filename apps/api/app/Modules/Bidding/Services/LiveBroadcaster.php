<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Services;

use App\Modules\Bidding\Broadcasting\IssuerLiveUpdated;
use App\Modules\Bidding\Broadcasting\OfferAcceptedBroadcast;
use App\Modules\Bidding\Broadcasting\ParticipantLiveUpdated;
use App\Modules\Bidding\Data\LastChange;
use App\Modules\Bidding\Data\OfferAcceptedContext;
use App\Modules\Bidding\Enums\LiveChangeKind;
use App\Modules\Bidding\Enums\OfferStage;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Competitions\Enums\RankVisibility;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use Illuminate\Support\Collection;

/**
 * Sends the live updates of ARCHITECTURE §7.10: a full, projected snapshot per audience, carrying
 * the live-state version `v`. The issuer channel always gets the issuer snapshot; participant
 * channels get their own snapshot only when they are in the recipient set.
 *
 * Offer recipients (§7.10): the bidder; with `leading_flag` the previous and the new leader;
 * with `full` every participant whose rank or flag changed; with `show_prices`, when the leading
 * amount changed, every participant. Offers outside stage `live` (initial, sealed, BAFO) change
 * nothing another participant may see, so only the bidder is told: a snapshot arriving would
 * otherwise reveal that someone bid.
 */
final readonly class LiveBroadcaster
{
    public function __construct(private VisibilityProjector $projector) {}

    public function offerAccepted(Offer $offer, Competition $competition, OfferAcceptedContext $context): void
    {
        $change = LastChange::of(LiveChangeKind::Offer);
        $view = $this->projector->load($competition);

        event(new IssuerLiveUpdated($competition->public_id, $this->projector->issuerSnapshot($competition, $change, $view)));

        $offer->loadMissing('participant.organization');
        event(new OfferAcceptedBroadcast($competition->public_id, $this->projector->offerLogEntry($offer, $competition, false)));

        $participants = $this->recipients($offer, $competition, $context);

        foreach ($participants as $participant) {
            event(new ParticipantLiveUpdated(
                $competition->public_id,
                $participant->organization->public_id,
                $this->projector->participantSnapshotFrom($view, $participant, $change),
            ));
        }
    }

    /**
     * The issuer and every joined participant (extension, status, BAFO, award, void).
     */
    public function toEveryone(Competition $competition, LastChange $change): void
    {
        $view = $this->projector->load($competition);

        event(new IssuerLiveUpdated($competition->public_id, $this->projector->issuerSnapshot($competition, $change, $view)));

        $participants = Participant::query()
            ->where('competition_id', $competition->id)
            ->with('organization')
            ->get();

        foreach ($participants as $participant) {
            event(new ParticipantLiveUpdated(
                $competition->public_id,
                $participant->organization->public_id,
                $this->projector->participantSnapshotFrom($view, $participant, $change),
            ));
        }
    }

    /**
     * @return Collection<int, Participant>
     */
    private function recipients(Offer $offer, Competition $competition, OfferAcceptedContext $context): Collection
    {
        $query = Participant::query()->where('competition_id', $competition->id)->with('organization');

        // CONTRACT-GAP: §7.10 lists the recipients per rank visibility without naming the stage.
        // Outside stage `live` another participant's projection does not change, and a snapshot
        // arriving would only reveal that someone bid, so only the bidder is told.
        if ($offer->stage !== OfferStage::Live) {
            return $query->whereKey($offer->participant_id)->get();
        }

        if ($competition->show_prices && $context->leadingAmountChanged) {
            return $query->get();
        }

        $ids = [$offer->participant_id];

        if ($competition->rank_visibility === RankVisibility::LeadingFlag && $context->leaderChanged) {
            // In stage `live` an offer can only make its own bidder the leader: the new leader
            // is the bidder, the previous one is in the context.
            $ids[] = $context->previousLeaderParticipantId;
        }

        if ($competition->rank_visibility === RankVisibility::Full) {
            $ids = [...$ids, ...$context->changedParticipantIds];
        }

        $ids = array_values(array_unique(array_filter($ids, static fn (?int $id): bool => $id !== null)));

        return $query->whereKey($ids)->get();
    }
}
