<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Competitions\Models\Invitation;
use App\Modules\Notifications\Services\CompetitionNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `invitation.declined` on `App\Modules\Competitions\Events\InvitationDeclined` (ARCHITECTURE §10, §11.3).
 */
final class NotifyInvitationDeclined extends QueuedNotificationListener
{
    public function __construct(private readonly CompetitionNotifier $notifier) {}

    /**
     * @param  object  $event  InvitationDeclined
     */
    public function handle(object $event): void
    {
        $this->notifier->invitationDeclined(EventPayload::instance($event, 'invitation', Invitation::class));
    }
}
