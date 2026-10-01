<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Data;

/**
 * What an accepted offer changed (ARCHITECTURE §10 `OfferAccepted`): the recipients of the
 * live update (§7.10), the webhook type (`offer.submitted` for the first offer, else
 * `offer.updated`) and the live-state version that includes the offer.
 */
final readonly class OfferAcceptedContext
{
    /**
     * @param  list<int>  $changedParticipantIds  internal participant ids (the bidder included)
     */
    public function __construct(
        public bool $isFirstOfferOfParticipant,
        public bool $leaderChanged,
        public ?int $previousLeaderParticipantId,
        public array $changedParticipantIds,
        public bool $extended,
        public int $version,
        // CONTRACT-GAP: not in the §10 property list. §7.10 sends the update to every participant
        // "when show_prices and the leading amount changed", which the listener cannot tell
        // after the fact; the engine records it here.
        public bool $leadingAmountChanged = false,
    ) {}
}
