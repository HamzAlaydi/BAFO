<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Data\ClaimResult;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Services\InvitationTokens;
use App\Modules\Identity\Contracts\OtpCodes;
use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * `POST /invitations/claim` (ARCHITECTURE §13.11): binds an invitation to the caller's
 * organization.
 *
 *   bound to another organization         409 `invitation_belongs_to_another_organization`
 *   the invited e-mail is the caller's    bound at once
 *   another e-mail, no code               an OTP (`invitation_claim`) goes to the invited e-mail → 202
 *   another e-mail, a valid code          bound
 */
final readonly class ClaimInvitation
{
    public function __construct(
        private InvitationTokens $tokens,
        private OtpCodes $otp,
    ) {}

    public function handle(string $token, ?string $code, User $user, Actor $actor): ClaimResult
    {
        $invitation = $this->tokens->find($token);
        $organizationId = $actor->organizationId;
        $competition = $invitation->competition()->firstOrFail();

        // CONTRACT-GAP: a member of the issuer cannot claim an invitation of its own competition;
        // it gets the same 409 as for an invitation bound to another organization.
        if ($organizationId === null
            || $organizationId === $competition->organization_id
            || ($invitation->organization_id !== null && $invitation->organization_id !== $organizationId)) {
            throw new ApiException(
                errorCode: 'invitation_belongs_to_another_organization',
                messageKey: 'competitions.errors.invitation_belongs_to_another_organization',
                status: 409,
            );
        }

        if ($invitation->organization_id === $organizationId) {
            return new ClaimResult($invitation);
        }

        if ($invitation->email !== mb_strtolower($user->email)) {
            if ($code === null || $code === '') {
                $otp = $this->otp->send(
                    email: $invitation->email,
                    purpose: OtpPurpose::InvitationClaim,
                    context: ['invitation_id' => $invitation->id],
                    locale: $user->locale,
                    ip: $actor->ip,
                );

                return new ClaimResult(null, InvitationTokens::maskEmail($invitation->email), $otp->expires_at);
            }

            // Outside the transaction, so that a wrong code still counts as an attempt.
            $otp = $this->otp->consume($invitation->email, OtpPurpose::InvitationClaim, $code);

            if (($otp->context['invitation_id'] ?? null) !== $invitation->id) {
                throw new ApiException(errorCode: 'otp_invalid', messageKey: 'competitions.errors.otp_invalid', status: 422);
            }
        }

        $bound = DB::transaction(static function () use ($invitation, $organizationId, $actor): Invitation {
            $locked = Invitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if ($locked->organization_id !== null && $locked->organization_id !== $organizationId) {
                throw new ApiException(
                    errorCode: 'invitation_belongs_to_another_organization',
                    messageKey: 'competitions.errors.invitation_belongs_to_another_organization',
                    status: 409,
                );
            }

            $locked->organization_id = $organizationId;
            $locked->save();

            AuditLogger::log('invitation.claimed', $locked, actor: $actor, organizationId: $organizationId);

            return $locked;
        });

        return new ClaimResult($bound);
    }
}
