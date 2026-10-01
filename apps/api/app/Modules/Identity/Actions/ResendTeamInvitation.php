<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Mail\TeamInvitationMail;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TeamInvitationTokens;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * `POST /team/members/{membership}/resend-invitation` (ARCHITECTURE §13.10): a new token and a new
 * 7-day expiry, so the old link stops working. Invited members only (409
 * `invalid_state_transition` otherwise).
 */
final readonly class ResendTeamInvitation
{
    public function __construct(private TeamInvitationTokens $tokens) {}

    public function handle(Membership $membership, User $inviter, Actor $actor): void
    {
        DB::transaction(function () use ($membership, $inviter, $actor): void {
            /** @var Membership $membership */
            $membership = Membership::query()->whereKey($membership->id)->lockForUpdate()->firstOrFail();

            if ($membership->status !== MembershipStatus::Invited) {
                throw new ApiException(
                    errorCode: 'invalid_state_transition',
                    status: 409,
                    details: ['status' => $membership->status->value],
                );
            }

            $membership->loadMissing(['user', 'organization']);
            $user = $membership->user;
            $organization = $membership->organization;

            $token = $this->tokens->generate();
            $expiresAt = Date::now()->addDays($this->tokens->ttlDays());

            $membership->forceFill([
                'invite_token_hash' => $this->tokens->hash($token),
                'invite_expires_at' => $expiresAt,
            ])->save();

            AuditLogger::log('member.invitation_resent', $membership, actor: $actor, organizationId: $membership->organization_id);

            $locale = $user->locale;

            Mail::to($user->email)->queue((new TeamInvitationMail(
                inviteeName: $user->name,
                organizationName: $organization->name,
                inviterName: $inviter->name,
                role: $membership->role,
                acceptUrl: $this->tokens->acceptUrl($token, $locale),
                expiresAt: $expiresAt,
            ))->locale($locale));
        });
    }
}
