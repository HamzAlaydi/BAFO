<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Events;

use App\Modules\Bidding\Models\Award;
use Illuminate\Queue\SerializesModels;

/**
 * The issuer's ERP reported the award as synced or failed (API.md §3.5 `POST /awards/{award}/erp-sync`).
 */
final readonly class AwardErpSynced
{
    use SerializesModels;

    public function __construct(
        public Award $award,
    ) {}
}
