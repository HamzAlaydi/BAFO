<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Exceptions\IdentityError;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AuthTokens;
use App\Modules\Identity\Services\OtpService;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * `POST /auth/password/reset` (ARCHITECTURE §13.9): consumes the `password_reset` code, sets the
 * new password and revokes **all** tokens of the user.
 */
final readonly class ResetPassword
{
    public function __construct(
        private OtpService $otp,
        private AuthTokens $tokens,
    ) {}

    public function handle(string $email, string $code, string $password, Actor $actor): void
    {
        // Outside the transaction: a wrong code must count as an attempt.
        $this->otp->consume($email, OtpPurpose::PasswordReset, $code);

        DB::transaction(function () use ($email, $password, $actor): void {
            $user = User::query()->where('email', mb_strtolower(trim($email)))->lockForUpdate()->first()
                ?? throw IdentityError::make('otp_invalid');

            $user->forceFill(['password' => $password])->save();
            $this->tokens->revokeAll($user);

            AuditLogger::log('user.password_reset', $user, actor: $actor, organizationId: $user->membership?->organization_id);
        });
    }
}
