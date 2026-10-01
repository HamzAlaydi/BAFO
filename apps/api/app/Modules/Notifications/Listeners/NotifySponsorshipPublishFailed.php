<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\Payment;
use App\Modules\Notifications\Services\BillingNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `sponsorship.publish_failed` on `App\Modules\Billing\Events\SponsorshipPublishFailed` (ARCHITECTURE §10, §11.3).
 */
final class NotifySponsorshipPublishFailed extends QueuedNotificationListener
{
    public function __construct(private readonly BillingNotifier $notifier) {}

    /**
     * @param  object  $event  SponsorshipPublishFailed
     */
    public function handle(object $event): void
    {
        $this->notifier->sponsorshipPublishFailed(
            EventPayload::instance($event, 'sponsorship', CompetitionSponsorship::class),
            EventPayload::instance($event, 'payment', Payment::class),
            EventPayload::string($event, 'errorCode'),
        );
    }
}
