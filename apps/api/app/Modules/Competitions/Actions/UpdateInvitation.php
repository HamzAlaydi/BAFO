<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Services\StateMachine;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * `PATCH …/invitations/{invitation}` (API.md §1.4): the contact `name` while the invitation is a
 * draft; `sponsored` (the issuer's "cover fees" tick) while the invitation is a draft or, after
 * that, as long as no pass exists for it. Otherwise 409 `invalid_state_transition`.
 */
final class UpdateInvitation
{
    /**
     * @param  array{name?: string|null, sponsored?: bool}  $changes
     */
    public function handle(Invitation $invitation, array $changes, Actor $actor): Invitation
    {
        return DB::transaction(static function () use ($invitation, $changes, $actor): Invitation {
            $locked = Invitation::query()->with('competition')->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            $isDraft = $locked->status === InvitationStatus::Draft;

            if (array_key_exists('name', $changes) && ! $isDraft) {
                throw StateMachine::invalidInvitation($locked->status, $locked->status);
            }

            if (array_key_exists('sponsored', $changes) && ! $isDraft) {
                $hasPass = SponsoredPass::query()
                    ->where('invitation_id', $locked->id)
                    ->whereIn('status', [PassStatus::Pending->value, PassStatus::Reserved->value, PassStatus::Joined->value])
                    ->exists();

                if ($hasPass || ! in_array($locked->status, [InvitationStatus::Sent, InvitationStatus::Viewed], true)) {
                    throw StateMachine::invalidInvitation($locked->status, $locked->status);
                }
            }

            if (array_key_exists('name', $changes)) {
                $name = $changes['name'] !== null ? trim($changes['name']) : null;
                $locked->name = $name === '' ? null : $name;
            }

            if (array_key_exists('sponsored', $changes)) {
                $locked->sponsored_requested = (bool) $changes['sponsored'];
            }

            if ($locked->isDirty()) {
                $locked->save();

                AuditLogger::log('invitation.updated', $locked, AuditLogger::diff($locked, ['name', 'sponsored_requested']),
                    actor: $actor, organizationId: $locked->competition->organization_id);
            }

            return $locked;
        });
    }
}
