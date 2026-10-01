<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Competitions\Models\Invitation;
use App\Modules\Notifications\Services\CompetitionNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `competition.invited` on `App\Modules\Competitions\Events\InvitationSent` (ARCHITECTURE §10, §11.3).
 */
final class NotifyInvitationSent extends QueuedNotificationListener
{
    public function __construct(private readonly CompetitionNotifier $notifier) {}

    /**
     * @param  object  $event  InvitationSent
     */
    public function handle(object $event): void
    {
        $this->notifier->invited(EventPayload::instance($event, 'invitation', Invitation::class));
    }
}
