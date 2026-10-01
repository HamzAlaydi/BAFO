<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\OtpService;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;

/**
 * `POST /auth/password/forgot` (ARCHITECTURE §13.9): sends a `password_reset` OTP when the user
 * exists (a pending team member without a password accepts the invitation instead). The endpoint
 * always answers 202, so a cooldown is swallowed here: the previous code is still valid.
 */
final readonly class RequestPasswordReset
{
    public function __construct(private OtpService $otp) {}

    public function handle(string $email, Actor $actor): void
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

        if ($user === null || $user->password === null) {
            return;
        }

        try {
            $this->otp->send($user->email, OtpPurpose::PasswordReset, $user, [], $user->locale, $actor->ip);
        } catch (ApiException $e) {
            if ($e->errorCode !== 'otp_resend_cooldown') {
                throw $e;
            }
        }
    }
}
