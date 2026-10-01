<?php

declare(strict_types=1);

namespace App\Modules\Identity\Exceptions;

use App\Support\Exceptions\ApiException;

/**
 * The Identity business errors of CONVENTIONS §8.3, each with its one HTTP status. Messages come
 * from `identity.errors.<code>`.
 */
final class IdentityError
{
    /**
     * @var array<string, int>
     */
    public const array STATUS = [
        'invalid_credentials' => 401,
        'email_not_verified' => 403,
        'account_inactive' => 403,
        'organization_suspended' => 403,
        'otp_invalid' => 422,
        'otp_expired' => 422,
        'otp_too_many_attempts' => 429,
        'otp_resend_cooldown' => 429,
        'password_incorrect' => 422,
        'team_invitation_invalid' => 422,
        'seat_limit_reached' => 409,
        'cannot_modify_owner' => 409,
        'cannot_modify_self' => 409,
        'account_deletion_blocked' => 409,
        'account_deletion_pending' => 409,
        'invitation_email_mismatch' => 422,
    ];

    /**
     * @param  array<string, mixed>  $details
     */
    public static function make(string $code, array $details = []): ApiException
    {
        return new ApiException(
            errorCode: $code,
            messageKey: 'identity.errors.'.$code,
            status: self::STATUS[$code] ?? 400,
            details: $details,
        );
    }
}
