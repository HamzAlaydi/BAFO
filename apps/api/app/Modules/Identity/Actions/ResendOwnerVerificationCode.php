<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Identity\Services\OtpService;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;

/**
 * Admin panel (ARCHITECTURE §16): sends the e-mail verification OTP to the organization owner
 * again. Null when the owner is already verified. The resend cooldown applies (429
 * `otp_resend_cooldown`).
 */
final readonly class ResendOwnerVerificationCode
{
    public function __construct(private OtpService $otp) {}

    public function handle(Organization $organization, Actor $actor): ?OtpCode
    {
        $owner = Membership::query()
            ->where('organization_id', $organization->id)
            ->where('role', OrgRole::Owner->value)
            ->with('user')
            ->first()
            ?->user;

        if ($owner === null || $owner->hasVerifiedEmail()) {
            return null;
        }

        $otp = $this->otp->send($owner->email, OtpPurpose::EmailVerification, $owner, [], $owner->locale, $actor->ip);

        AuditLogger::log('user.verification_code_resent', $owner, actor: $actor, organizationId: $organization->id);

        return $otp;
    }
}
