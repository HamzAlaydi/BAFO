<?php

declare(strict_types=1);

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A sponsorship payment succeeded but the publish (or invite) it pays for was refused
 * (ARCHITECTURE §10, §13.5). The funded passes stay; the issuer fixes the problem and tries
 * again without paying again. Listener: Notifications `sponsorship.publish_failed`.
 */
final readonly class SponsorshipPublishFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CompetitionSponsorship $sponsorship,
        public Payment $payment,
        public string $errorCode,
    ) {}
}
