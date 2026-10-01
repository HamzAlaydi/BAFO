<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Events\EmailVerified;
use App\Modules\Identity\Exceptions\IdentityError;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccountGate;
use App\Modules\Identity\Services\AuthTokens;
use App\Modules\Identity\Services\OtpService;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * `POST /auth/otp/verify` (ARCHITECTURE §13.9): consumes the e-mail verification code, sets
 * `email_verified_at` and status `active`, dispatches `EmailVerified` and signs the user in.
 */
final readonly class VerifyEmail
{
    public function __construct(
        private OtpService $otp,
        private AccountGate $gate,
        private AuthTokens $tokens,
    ) {}

    /**
     * @return array{user: User, token: string}
     */
    public function handle(string $email, string $code, string $deviceName, Actor $actor): array
    {
        // Outside the transaction: a wrong code must count as an attempt.
        $this->otp->consume($email, OtpPurpose::EmailVerification, $code);

        $user = DB::transaction(function () use ($email, $actor): User {
            $user = User::query()->where('email', mb_strtolower(trim($email)))->lockForUpdate()->first()
                ?? throw IdentityError::make('otp_invalid');

            if (! $user->hasVerifiedEmail() || $user->status === UserStatus::PendingVerification) {
                $user->forceFill([
                    'email_verified_at' => $user->email_verified_at ?? Date::now(),
                    'status' => UserStatus::Active,
                ])->save();

                AuditLogger::log('user.email_verified', $user, actor: $actor, organizationId: $user->membership?->organization_id);

                event(new EmailVerified($user));
            }

            return $user;
        });

        $this->gate->assertCanSignIn($user);

        $token = DB::transaction(fn (): string => $this->tokens->issue($user, $deviceName));

        return ['user' => $user, 'token' => $token];
    }
}
