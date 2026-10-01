<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * `otp_codes.purpose` (ARCHITECTURE §5.3, §13.9).
 */
enum OtpPurpose: string
{
    case EmailVerification = 'email_verification';
    case PasswordReset = 'password_reset';
    case InvitationClaim = 'invitation_claim';

    /** Label in the given locale (default: the app locale). Key: `identity.enums.otp_purpose.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('identity.enums.otp_purpose.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
