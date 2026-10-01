<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Data\Viewer;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Events\CommentPosted;
use App\Modules\Competitions\Models\Comment;
use App\Modules\Competitions\Models\Competition;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * A Q&A message (API.md §1.4 `POST …/comments`, ARCHITECTURE §8.3). Only while the competition
 * is scheduled or live (409 `comments_closed`).
 *
 *   participant  a top-level question, or a reply under its own organization's question
 *   issuer       a reply under any question, or a top-level announcement
 *
 * One level of replies: a reply's parent is always a top-level comment.
 */
final class PostComment
{
    public function handle(Competition $competition, Viewer $viewer, string $body, ?Comment $parent, Actor $actor): Comment
    {
        return DB::transaction(static function () use ($competition, $viewer, $body, $parent, $actor): Comment {
            $locked = Competition::query()->whereKey($competition->id)->sharedLock()->firstOrFail();

            if (! in_array($locked->status, [CompetitionStatus::Scheduled, CompetitionStatus::Live], true)) {
                throw new ApiException(
                    errorCode: 'comments_closed',
                    messageKey: 'competitions.errors.comments_closed',
                    status: 409,
                );
            }

            if (! $viewer->isIssuer() && ! $viewer->isParticipant()) {
                throw new ApiException(
                    errorCode: 'not_a_participant',
                    messageKey: 'competitions.errors.not_a_participant',
                    status: 403,
                );
            }

            if ($parent !== null && $viewer->isParticipant() && $parent->author_organization_id !== $viewer->organizationId) {
                throw new ApiException(errorCode: 'forbidden', status: 403);
            }

            $comment = new Comment;
            $comment->forceFill([
                'competition_id' => $locked->id,
                'parent_id' => $parent?->id,
                'author_user_id' => $actor->userId,
                'author_organization_id' => $viewer->organizationId,
                'author_participant_id' => $viewer->participant?->id,
                'is_issuer' => $viewer->isIssuer(),
                'body' => trim($body),
            ])->save();

            AuditLogger::log('comment.posted', $comment, meta: ['competition_id' => $locked->public_id, 'reply' => $parent !== null],
                actor: $actor, organizationId: $viewer->organizationId);

            event(new CommentPosted($comment, $actor));

            return $comment;
        });
    }
}
