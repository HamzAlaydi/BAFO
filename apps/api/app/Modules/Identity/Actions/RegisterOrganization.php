<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Data\RegistrationData;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Identity\Exceptions\IdentityError;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\ConsentRecorder;
use App\Modules\Identity\Services\OtpService;
use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * `POST /auth/register` (ARCHITECTURE §13.9): creates the organization (active), the owner user
 * (`pending_verification`), the owner membership (active), the terms and privacy consents and
 * the category pivots, then sends the e-mail verification OTP. No token yet.
 */
final readonly class RegisterOrganization
{
    public function __construct(
        private OtpService $otp,
        private ConsentRecorder $consents,
    ) {}

    /**
     * @return array{user: User, organization: Organization, otp: OtpCode}
     */
    public function handle(RegistrationData $data, Actor $actor): array
    {
        if ($data->invitationEmail !== null && mb_strtolower($data->invitationEmail) !== mb_strtolower($data->email)) {
            throw IdentityError::make('invitation_email_mismatch');
        }

        return DB::transaction(function () use ($data, $actor): array {
            $organization = Organization::query()->create([
                ...$data->organization->attributes,
                'email' => $data->email,
                'phone' => $data->phone,
                'status' => OrganizationStatus::Active,
            ]);

            if ($data->organization->categoryIds !== null) {
                $organization->categories()->sync($data->organization->categoryIds);
            }

            $user = User::query()->create([
                'name' => $data->name,
                'email' => $data->email,
                'phone' => $data->phone,
                'password' => $data->password,
                'locale' => $data->locale,
                'status' => UserStatus::PendingVerification,
            ]);

            Membership::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'role' => OrgRole::Owner,
                'can_award' => true,
                'can_purchase' => true,
                'status' => MembershipStatus::Active,
                'joined_at' => Date::now(),
            ]);

            $this->consents->record(
                $user,
                $organization->id,
                [LegalDocumentCode::Terms, LegalDocumentCode::Privacy],
                $data->locale,
                $actor,
            );

            AuditLogger::log(
                'organization.registered',
                $organization,
                meta: ['user_id' => $user->public_id],
                actor: $actor,
                organizationId: $organization->id,
            );

            event(new UserRegistered($user, $organization));

            $otp = $this->otp->send($user->email, OtpPurpose::EmailVerification, $user, [], $data->locale, $actor->ip);

            return ['user' => $user, 'organization' => $organization, 'otp' => $otp];
        });
    }
}
