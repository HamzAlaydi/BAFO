<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Enums\RevokeReason;
use App\Modules\Competitions\Events\CompetitionUpdated;
use App\Modules\Competitions\Events\InvitationRevoked;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Services\StateMachine;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * sent / viewed → revoked (ARCHITECTURE §6.2): by the issuer, an admin, or a duplicate
 * organization at join (§13.5). Billing releases the invitation's pass synchronously on
 * InvitationRevoked.
 */
final class RevokeInvitation
{
    public function handle(Invitation $invitation, Actor $actor, ?RevokeReason $reason = null): Invitation
    {
        return DB::transaction(static function () use ($invitation, $actor, $reason): Invitation {
            $locked = Invitation::query()->with('competition')->whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [InvitationStatus::Sent, InvitationStatus::Viewed], true)) {
                throw StateMachine::invalidInvitation($locked->status, InvitationStatus::Revoked);
            }

            $reason ??= $actor->isAdmin() ? RevokeReason::Admin : RevokeReason::Issuer;

            $locked->forceFill([
                'status' => InvitationStatus::Revoked,
                'revoked_at' => Date::now(),
                'revoke_reason' => $reason,
            ])->save();

            AuditLogger::log('invitation.revoked', $locked, meta: ['reason' => $reason->value], actor: $actor,
                organizationId: $locked->competition->organization_id);

            event(new InvitationRevoked($locked, $actor));

            if ($reason !== RevokeReason::DuplicateOrganization) {
                event(new CompetitionUpdated($locked->competition, ['invitations'], $actor));
            }

            return $locked;
        });
    }
}
