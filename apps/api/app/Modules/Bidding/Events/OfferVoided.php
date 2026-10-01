<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Events;

use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\OfferVoid;
use App\Modules\Competitions\Models\Competition;
use Illuminate\Queue\SerializesModels;

/**
 * A platform admin voided an offer (ARCHITECTURE §7.13, §10). The ledger row is unchanged.
 */
final readonly class OfferVoided
{
    use SerializesModels;

    public function __construct(
        public OfferVoid $void,
        public Offer $offer,
        public Competition $competition,
    ) {}
}
