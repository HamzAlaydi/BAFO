<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Notifications\Services\BillingNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `sponsorship.unused_passes` on `App\Modules\Billing\Events\SponsorshipSettled` (ARCHITECTURE §10, §11.3).
 */
final class NotifySponsorshipSettled extends QueuedNotificationListener
{
    public function __construct(private readonly BillingNotifier $notifier) {}

    /**
     * @param  object  $event  SponsorshipSettled
     */
    public function handle(object $event): void
    {
        $this->notifier->sponsorshipSettled(EventPayload::instance($event, 'sponsorship', CompetitionSponsorship::class));
    }
}
