<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Listeners;

use App\Modules\Competitions\Broadcasting\CommentCreatedBroadcast;
use App\Modules\Competitions\Events\CommentPosted;
use App\Modules\Competitions\Http\Resources\CommentResource;
use App\Modules\Competitions\Models\Comment;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Competitions\Services\CompetitionChannels;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Http\Request;

/**
 * `comment.created` to the issuer channel and to every participant channel, each payload
 * projected for its audience (API.md §2.7 author rules).
 */
final class BroadcastCommentCreated implements ShouldHandleEventsAfterCommit, ShouldQueueAfterCommit
{
    public string $queue = 'live';

    public function handle(CommentPosted $event): void
    {
        $comment = Comment::query()
            ->with(['competition', 'authorOrganization', 'authorParticipant', 'parent'])
            ->find($event->comment->id);

        if ($comment === null) {
            return;
        }

        $competition = $comment->competition;
        $request = Request::create('/');

        broadcast(new CommentCreatedBroadcast(
            CompetitionChannels::issuer($competition),
            (new CommentResource($comment, $competition->organization_id, true))->resolve($request),
        ));

        $participants = Participant::query()->with('organization')->where('competition_id', $competition->id)->get();

        foreach ($participants as $participant) {
            broadcast(new CommentCreatedBroadcast(
                CompetitionChannels::participant($competition, $participant->organization->public_id),
                (new CommentResource($comment, $participant->organization_id, false))->resolve($request),
            ));
        }
    }
}
