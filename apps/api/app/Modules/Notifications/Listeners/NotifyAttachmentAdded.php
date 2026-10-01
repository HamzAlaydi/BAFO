<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Competitions\Models\CompetitionAttachment;
use App\Modules\Notifications\Services\CompetitionNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `competition.updated` (addendum on a published competition) on `App\Modules\Competitions\Events\AttachmentAdded` (ARCHITECTURE §10, §11.3).
 */
final class NotifyAttachmentAdded extends QueuedNotificationListener
{
    public function __construct(private readonly CompetitionNotifier $notifier) {}

    /**
     * @param  object  $event  AttachmentAdded
     */
    public function handle(object $event): void
    {
        $this->notifier->attachmentAdded(EventPayload::instance($event, 'attachment', CompetitionAttachment::class));
    }
}
