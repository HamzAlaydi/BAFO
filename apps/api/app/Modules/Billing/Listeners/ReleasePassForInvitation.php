<?php

declare(strict_types=1);

namespace App\Modules\Billing\Listeners;

use App\Modules\Billing\Actions\ReleaseSponsoredPass;
use App\Modules\Billing\Enums\PassReleaseReason;
use App\Modules\Competitions\Enums\RevokeReason;
use App\Modules\Competitions\Events\InvitationDeclined;
use App\Modules\Competitions\Events\InvitationRevoked;
use App\Support\Auth\CurrentActor;

/**
 * Synchronous, in-transaction listener (ARCHITECTURE §4.10, §10, §13.5 "Release") on the
 * Competitions events InvitationDeclined and InvitationRevoked: the invitation's reserved pass
 * is released (its slot is freed) and a pending pass is voided. DB-only.
 */
final readonly class ReleasePassForInvitation
{
    public function __construct(private ReleaseSponsoredPass $release) {}

    public function onDeclined(InvitationDeclined $event): void
    {
        $this->release->handle($event->invitation, PassReleaseReason::Declined, CurrentActor::get());
    }

    public function onRevoked(InvitationRevoked $event): void
    {
        $reason = $event->invitation->revoke_reason === RevokeReason::DuplicateOrganization
            ? PassReleaseReason::DuplicateOrganization
            : PassReleaseReason::Revoked;

        $this->release->handle($event->invitation, $reason, $event->actor);
    }
}
