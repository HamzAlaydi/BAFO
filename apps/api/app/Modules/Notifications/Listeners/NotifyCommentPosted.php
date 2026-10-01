<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Competitions\Models\Comment;
use App\Modules\Notifications\Services\CompetitionNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `comment.created` on `App\Modules\Competitions\Events\CommentPosted` (ARCHITECTURE §10, §11.3).
 */
final class NotifyCommentPosted extends QueuedNotificationListener
{
    public function __construct(private readonly CompetitionNotifier $notifier) {}

    /**
     * @param  object  $event  CommentPosted
     */
    public function handle(object $event): void
    {
        $this->notifier->commentPosted(EventPayload::instance($event, 'comment', Comment::class));
    }
}
