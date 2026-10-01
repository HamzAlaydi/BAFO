<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Events\CompetitionUpdated;
use App\Modules\Competitions\Events\InvitationDeclined;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Services\StateMachine;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * sent / viewed → declined (ARCHITECTURE §6.2, §13.11), in the app or by token from the e-mail.
 * Billing releases the invitation's pass synchronously on InvitationDeclined.
 */
final class DeclineInvitation
{
    public function handle(Invitation $invitation, ?string $reason, Actor $actor): Invitation
    {
        return DB::transaction(static function () use ($invitation, $reason, $actor): Invitation {
            $locked = Invitation::query()->with('competition')->whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [InvitationStatus::Sent, InvitationStatus::Viewed], true)) {
                throw StateMachine::invalidInvitation($locked->status, InvitationStatus::Declined);
            }

            $reason = $reason !== null ? trim($reason) : null;

            $locked->forceFill([
                'status' => InvitationStatus::Declined,
                'declined_at' => Date::now(),
                'decline_reason' => $reason === '' ? null : $reason,
            ])->save();

            AuditLogger::log('invitation.declined', $locked, actor: $actor,
                organizationId: $locked->organization_id ?? $locked->competition->organization_id);

            event(new InvitationDeclined($locked));
            event(new CompetitionUpdated($locked->competition, ['invitations'], $actor));

            return $locked;
        });
    }
}
