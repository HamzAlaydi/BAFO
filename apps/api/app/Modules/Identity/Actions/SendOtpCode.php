<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\OtpService;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/**
 * `POST /auth/otp/send` (API.md §1.3): (re)sends an e-mail verification code to an unverified
 * user, or a password reset code to an existing user. Returns the code's expiry.
 *
 * Other e-mails (unknown, already verified, a pending team member) get no mail, but the answer
 * is the same as for a real account (SECURITY_REVIEW S-01): a plausible `otp_expires_at`, and the
 * same 60-second cooldown and hourly cap (429 `otp_resend_cooldown`), kept in the cache. So neither
 * the body nor the rate limits reveal whether an account exists.
 *
 * A pending team member (no password yet) gets neither: the invitation link verifies the e-mail
 * and sets the password (§13.10).
 */
final readonly class SendOtpCode
{
    public function __construct(private OtpService $otp) {}

    public function handle(string $email, OtpPurpose $purpose, Actor $actor): CarbonImmutable
    {
        $email = mb_strtolower(trim($email));
        $user = User::query()->where('email', $email)->first();

        $eligible = $user !== null && $user->password !== null && match ($purpose) {
            OtpPurpose::EmailVerification => ! $user->hasVerifiedEmail(),
            OtpPurpose::PasswordReset => true,
            OtpPurpose::InvitationClaim => false,
        };

        if (! $eligible) {
            return $this->decoy($email, $purpose);
        }

        return CarbonImmutable::instance($this->otp->send($user->email, $purpose, $user, [], $user->locale, $actor->ip)->expires_at);
    }

    /**
     * The answer for an address that gets no code, with OtpService's cooldown and hourly cap.
     */
    private function decoy(string $email, OtpPurpose $purpose): CarbonImmutable
    {
        $now = CarbonImmutable::instance(Date::now());
        $key = 'otp-decoy:'.hash('sha256', $email);

        /** @var list<array{0: int, 1: string}> $sends */
        $sends = array_values(array_filter(
            (array) Cache::get($key, []),
            static fn (mixed $send): bool => is_array($send) && is_int($send[0] ?? null) && $send[0] > $now->getTimestamp() - 3600,
        ));

        $lastOfPurpose = max([0, ...array_map(static fn (array $send): int => $send[1] === $purpose->value ? $send[0] : 0, $sends)]);
        $cooldown = self::intConfig('resend_cooldown_seconds', 60);

        if ($lastOfPurpose > 0 && $lastOfPurpose + $cooldown > $now->getTimestamp()) {
            throw self::cooldown($lastOfPurpose + $cooldown - $now->getTimestamp());
        }

        if (count($sends) >= self::intConfig('max_sends_per_hour', 5)) {
            throw self::cooldown(min(array_column($sends, 0)) + 3600 - $now->getTimestamp());
        }

        $sends[] = [$now->getTimestamp(), $purpose->value];
        Cache::put($key, $sends, $now->addHour());

        return $now->addMinutes(self::intConfig('ttl_minutes', 10));
    }

    private static function cooldown(int $seconds): ApiException
    {
        $seconds = max(1, $seconds);

        return new ApiException(
            errorCode: 'otp_resend_cooldown',
            messageKey: 'identity.errors.otp_resend_cooldown',
            status: 429,
            replace: ['seconds' => $seconds],
            headers: ['Retry-After' => (string) $seconds],
            details: ['retry_after_seconds' => $seconds],
        );
    }

    private static function intConfig(string $key, int $default): int
    {
        $value = config('bafo.identity.otp.'.$key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
