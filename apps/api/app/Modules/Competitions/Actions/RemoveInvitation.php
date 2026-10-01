<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Services\StateMachine;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * `DELETE …/invitations/{invitation}` (API.md §1.4, §3.4): a draft invitation of a draft
 * competition is deleted; a sent or viewed one is revoked; anything else is 409.
 */
final readonly class RemoveInvitation
{
    public function __construct(private RevokeInvitation $revoke) {}

    /**
     * @return Invitation|null the revoked invitation, or null when it was deleted
     */
    public function handle(Invitation $invitation, Actor $actor): ?Invitation
    {
        if ($invitation->status !== InvitationStatus::Draft) {
            return $this->revoke->handle($invitation, $actor);
        }

        DB::transaction(static function () use ($invitation, $actor): void {
            $locked = Invitation::query()->with('competition')->whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== InvitationStatus::Draft || $locked->competition->status !== CompetitionStatus::Draft) {
                throw StateMachine::invalidInvitation($locked->status, InvitationStatus::Revoked);
            }

            AuditLogger::log('invitation.deleted', $locked, meta: ['email' => $locked->email], actor: $actor,
                organizationId: $locked->competition->organization_id);

            $locked->delete();
        });

        return null;
    }
}
