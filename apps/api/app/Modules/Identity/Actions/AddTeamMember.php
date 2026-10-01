<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Data\TeamMemberData;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Events\MemberAdded;
use App\Modules\Identity\Mail\TeamInvitationMail;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\MembershipFlagGuard;
use App\Modules\Identity\Services\SeatGuard;
use App\Modules\Identity\Services\TeamInvitationTokens;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * `POST /team/members` (ARCHITECTURE §13.10): checks the seats, creates the user
 * (`pending_verification`, no password) and an `invited` membership with a 7-day token, and
 * mails the invitation link. Admins default to `can_award` and `can_purchase`; members to
 * neither (§8.1). The inviter grants only the flags it holds itself (MembershipFlagGuard): an
 * explicit grant it does not hold is 422, and the admin defaults stop at its own flags.
 */
final readonly class AddTeamMember
{
    public function __construct(
        private SeatGuard $seats,
        private TeamInvitationTokens $tokens,
        private MembershipFlagGuard $flags,
    ) {}

    public function handle(Organization $organization, TeamMemberData $data, User $inviter, Actor $actor): Membership
    {
        $this->flags->assertGrantable($inviter, ['can_award' => $data->canAward, 'can_purchase' => $data->canPurchase]);

        return DB::transaction(function () use ($organization, $data, $inviter, $actor): Membership {
            $organization = $this->seats->lockAndAssertFreeSeat($organization);

            $locale = App::getLocale();
            $privileged = $data->role === OrgRole::Admin;

            $user = User::query()->create([
                'name' => $data->name,
                'email' => $data->email,
                'phone' => $data->phone,
                'password' => null,
                'locale' => $locale,
                'status' => UserStatus::PendingVerification,
            ]);

            $token = $this->tokens->generate();
            $expiresAt = Date::now()->addDays($this->tokens->ttlDays());

            $membership = Membership::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'role' => $data->role,
                'can_award' => $data->canAward ?? ($privileged && $this->flags->holds($inviter, 'can_award')),
                'can_purchase' => $data->canPurchase ?? ($privileged && $this->flags->holds($inviter, 'can_purchase')),
                'status' => MembershipStatus::Invited,
                'invited_by_user_id' => $inviter->id,
                'invite_token_hash' => $this->tokens->hash($token),
                'invite_expires_at' => $expiresAt,
            ]);

            $membership->setRelation('user', $user);
            $membership->setRelation('organization', $organization);

            AuditLogger::log('member.added', $membership, meta: [
                'user_id' => $user->public_id,
                'email' => $user->email,
                'role' => $data->role->value,
            ], actor: $actor, organizationId: $organization->id);

            event(new MemberAdded($membership, $actor));

            Mail::to($user->email)->queue((new TeamInvitationMail(
                inviteeName: $user->name,
                organizationName: $organization->name,
                inviterName: $inviter->name,
                role: $data->role,
                acceptUrl: $this->tokens->acceptUrl($token, $locale),
                expiresAt: $expiresAt,
            ))->locale($locale));

            return $membership;
        });
    }
}
