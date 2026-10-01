<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Listeners;

use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Events\EmailVerified;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * When a user verifies an e-mail (ARCHITECTURE §10 `Identity\EmailVerified`), the sent or viewed
 * invitations addressed to it without an organization get the user's organization, so they
 * appear in its "participating" list.
 */
final class AttachPendingInvitations implements ShouldHandleEventsAfterCommit, ShouldQueueAfterCommit
{
    public string $queue = 'default';

    public function handle(EmailVerified $event): void
    {
        $user = $event->user->loadMissing('membership');
        $organizationId = $user->membership?->organization_id;

        if ($organizationId === null) {
            return;
        }

        Invitation::query()
            ->where('email', mb_strtolower($user->email))
            ->whereNull('organization_id')
            ->whereIn('status', [InvitationStatus::Sent->value, InvitationStatus::Viewed->value])
            // Never bind an issuer to an invitation of its own competition.
            ->whereHas('competition', static fn ($q) => $q->where('organization_id', '!=', $organizationId))
            ->update(['organization_id' => $organizationId, 'updated_at' => now()]);
    }
}
