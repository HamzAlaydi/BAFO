<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Events\InvitationViewed;
use App\Modules\Competitions\Models\Invitation;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * sent → viewed (ARCHITECTURE §6.2): the invitee organization opens the competition, or the
 * token lookup is called. Idempotent: any other status is left as it is.
 */
final class MarkInvitationViewed
{
    public function handle(Invitation $invitation, Actor $actor): Invitation
    {
        if ($invitation->status !== InvitationStatus::Sent) {
            return $invitation;
        }

        return DB::transaction(static function () use ($invitation, $actor): Invitation {
            $locked = Invitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== InvitationStatus::Sent) {
                return $locked;
            }

            $locked->forceFill(['status' => InvitationStatus::Viewed, 'viewed_at' => Date::now()])->save();

            AuditLogger::log('invitation.viewed', $locked, actor: $actor,
                organizationId: $locked->organization_id ?? $actor->organizationId);

            event(new InvitationViewed($locked));

            return $locked;
        });
    }
}
