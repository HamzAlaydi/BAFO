<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Listeners;

use App\Modules\Competitions\Broadcasting\InvitationUpdatedBroadcast;
use App\Modules\Competitions\Events\InvitationDeclined;
use App\Modules\Competitions\Events\InvitationJoined;
use App\Modules\Competitions\Events\InvitationRevoked;
use App\Modules\Competitions\Events\InvitationSent;
use App\Modules\Competitions\Events\InvitationsExpired;
use App\Modules\Competitions\Events\InvitationViewed;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Services\InvitationPresenter;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * `invitation.updated` on the issuer channel for every invitation status change (ARCHITECTURE §10).
 */
final class BroadcastInvitationUpdated implements ShouldHandleEventsAfterCommit, ShouldQueueAfterCommit
{
    public string $queue = 'live';

    public function __construct(private readonly InvitationPresenter $presenter) {}

    public function handle(InvitationSent|InvitationViewed|InvitationJoined|InvitationDeclined|InvitationRevoked|InvitationsExpired $event): void
    {
        $ids = $event instanceof InvitationsExpired ? $event->invitationIds : [$event->invitation->id];

        $invitations = Invitation::query()->with('competition')->whereKey($ids)->get();

        foreach ($invitations as $invitation) {
            broadcast(new InvitationUpdatedBroadcast(
                $invitation->competition->public_id,
                $this->presenter->issuerViewOf($invitation),
            ));
        }
    }
}
