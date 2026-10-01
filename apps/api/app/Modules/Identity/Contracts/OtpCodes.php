<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\ApiException;

/**
 * One-time codes sent by e-mail (ARCHITECTURE §5.3 `otp_codes`, §13.9). Identity uses it for
 * e-mail verification and password reset; Competitions uses it for the invitation claim
 * (purpose `invitation_claim`, `context.invitation_id`, §13.11).
 *
 * CONTRACT-GAP: §3.6 lists no Identity contract, but the invitation claim (Competitions) writes
 * `otp_codes`, which §3.3 rule 2 routes through a contract. Bound in IdentityServiceProvider.
 */
interface OtpCodes
{
    /**
     * Sends a new 6-digit code to the e-mail (10-minute lifetime) and invalidates the older
     * unconsumed codes of the same e-mail and purpose. The mail is queued after commit.
     *
     * @param  array<string, mixed>  $context  stored in `otp_codes.context`, e.g. ['invitation_id' => 12]
     *
     * @throws ApiException `otp_resend_cooldown` (429, `details.retry_after_seconds`): within 60 s of
     *                      the last code, or 5 codes to this e-mail in the last hour
     */
    public function send(
        string $email,
        OtpPurpose $purpose,
        ?User $user = null,
        array $context = [],
        ?string $locale = null,
        ?string $ip = null,
    ): OtpCode;

    /**
     * Checks the latest code without consuming it. A wrong code counts as an attempt.
     *
     * Call it outside your own transaction, so that failed attempts are kept.
     *
     * @throws ApiException `otp_invalid` (422), `otp_expired` (422), `otp_too_many_attempts` (429)
     */
    public function check(string $email, OtpPurpose $purpose, string $code): OtpCode;

    /**
     * Checks the latest code and consumes it. Same errors and the same transaction rule as check().
     *
     * @throws ApiException `otp_invalid` (422), `otp_expired` (422), `otp_too_many_attempts` (429)
     */
    public function consume(string $email, OtpPurpose $purpose, string $code): OtpCode;
}
