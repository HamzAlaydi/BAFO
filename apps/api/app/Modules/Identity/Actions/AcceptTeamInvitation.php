<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Events\MemberUpdated;
use App\Modules\Identity\Exceptions\IdentityError;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccountGate;
use App\Modules\Identity\Services\AuthTokens;
use App\Modules\Identity\Services\ConsentRecorder;
use App\Modules\Identity\Services\TeamInvitationTokens;
use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * `POST /auth/team-invitations/accept` (ARCHITECTURE §13.10): the link proves the e-mail, so the
 * user gets a password, `email_verified_at` and status `active`; the membership becomes `active`
 * and the token is spent. Signs the user in.
 */
final readonly class AcceptTeamInvitation
{
    public function __construct(
        private TeamInvitationTokens $invitations,
        private ConsentRecorder $consents,
        private AccountGate $gate,
        private AuthTokens $tokens,
    ) {}

    /**
     * @return array{user: User, token: string}
     */
    public function handle(string $token, string $password, string $deviceName, Actor $actor): array
    {
        return DB::transaction(function () use ($token, $password, $deviceName, $actor): array {
            $membership = $this->invitations->find($token, lock: true)
                ?? throw IdentityError::make('team_invitation_invalid');

            $membership->loadMissing(['user', 'organization']);
            $user = $membership->user;
            $now = Date::now();

            $user->forceFill([
                'password' => $password,
                'email_verified_at' => $user->email_verified_at ?? $now,
                'status' => UserStatus::Active,
            ])->save();

            $membership->forceFill([
                'status' => MembershipStatus::Active,
                'joined_at' => $now,
                'invite_token_hash' => null,
                'invite_expires_at' => null,
            ])->save();

            $changes = ['status' => ['from' => MembershipStatus::Invited->value, 'to' => MembershipStatus::Active->value]];

            $user->setRelation('membership', $membership);
            $this->gate->assertCanSignIn($user);

            $this->consents->record($user, $membership->organization_id, [LegalDocumentCode::Terms], $user->locale, $actor);

            AuditLogger::log('member.joined', $membership, $changes, actor: $actor, organizationId: $membership->organization_id);

            event(new MemberUpdated($membership, $actor, $changes));

            return ['user' => $user, 'token' => $this->tokens->issue($user, $deviceName)];
        });
    }
}
