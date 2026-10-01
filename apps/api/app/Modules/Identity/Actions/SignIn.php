<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Exceptions\IdentityError;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccountGate;
use App\Modules\Identity\Services\AuthTokens;
use App\Modules\Identity\Services\OtpService;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Http\Iso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * `POST /auth/login` (ARCHITECTURE §13.9). Errors in the contract order: `invalid_credentials`
 * (never says which part was wrong), `account_inactive`, `organization_suspended`, then
 * `email_not_verified`, which also (re)sends the verification OTP within the cooldown.
 */
final readonly class SignIn
{
    public function __construct(
        private AccountGate $gate,
        private AuthTokens $tokens,
        private OtpService $otp,
    ) {}

    /**
     * @return array{user: User, token: string}
     */
    public function handle(string $email, string $password, string $deviceName, Actor $actor): array
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();
        $hash = $user?->password;

        if ($user === null || $hash === null || $user->status === UserStatus::Deleted) {
            // Same work as a real check, so the response time does not reveal unknown e-mails.
            Hash::check($password, self::dummyHash());

            throw IdentityError::make('invalid_credentials');
        }

        if (! Hash::check($password, $hash)) {
            throw IdentityError::make('invalid_credentials');
        }

        $this->gate->assertCanSignIn($user);

        if (! $user->hasVerifiedEmail()) {
            $otp = $this->otp->sendRespectingCooldown($user->email, OtpPurpose::EmailVerification, $user, $user->locale, $actor->ip);

            throw IdentityError::make('email_not_verified', ['otp_expires_at' => Iso::format($otp?->expires_at)]);
        }

        $token = DB::transaction(function () use ($user, $deviceName, $actor): string {
            $token = $this->tokens->issue($user, $deviceName);

            AuditLogger::log('user.signed_in', $user, meta: ['device_name' => $deviceName], actor: $actor, organizationId: $user->membership?->organization_id);

            return $token;
        });

        return ['user' => $user, 'token' => $token];
    }

    private static function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make(Str::random(40));
    }
}
