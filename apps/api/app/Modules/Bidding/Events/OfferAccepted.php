<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Events;

use App\Modules\Bidding\Data\OfferAcceptedContext;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Competitions\Models\Competition;
use Illuminate\Queue\SerializesModels;

/**
 * An offer entered the ledger (ARCHITECTURE §7.4 step 21, §10). Dispatched inside the offer
 * transaction; the Integrations webhook writer listens synchronously, everything else is
 * queued after commit.
 */
final readonly class OfferAccepted
{
    use SerializesModels;

    public function __construct(
        public Offer $offer,
        public Competition $competition,
        public OfferAcceptedContext $context,
    ) {}
}
