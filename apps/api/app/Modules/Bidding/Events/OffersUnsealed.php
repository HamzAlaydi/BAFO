<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Events;

use App\Modules\Competitions\Models\Competition;
use Illuminate\Queue\SerializesModels;

/**
 * A sealed competition closed and its offers are now visible to the issuer (ARCHITECTURE §7.8,
 * §10). Dispatched by `BiddingEngine::finalizeLiveBidding()` inside the close transaction, after
 * the caller set `offers_opened_at`.
 */
final readonly class OffersUnsealed
{
    use SerializesModels;

    public function __construct(
        public Competition $competition,
    ) {}
}
