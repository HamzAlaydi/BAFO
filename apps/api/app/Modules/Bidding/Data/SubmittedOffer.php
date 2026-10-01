<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Data;

use App\Modules\Bidding\Models\Offer;

/**
 * The result of `SubmitOffer`: the accepted offer, or the original one on an idempotent replay
 * (API.md §1.6: 201, or 200 with `Idempotent-Replayed: true`).
 */
final readonly class SubmittedOffer
{
    public function __construct(
        public Offer $offer,
        public bool $replayed,
    ) {}
}
