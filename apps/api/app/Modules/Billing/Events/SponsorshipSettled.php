<?php

declare(strict_types=1);

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\CompetitionSponsorship;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A competition's sponsorship was settled at close or cancel (ARCHITECTURE §10, §13.5). Listener: Notifications `sponsorship.unused_passes` when `unused_count > 0`.
 */
final readonly class SponsorshipSettled
{
    use Dispatchable, SerializesModels;

    public function __construct(public CompetitionSponsorship $sponsorship) {}
}
