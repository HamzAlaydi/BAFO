<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Contracts\OtpCodes;
use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Mail\OtpCodeMail;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * The OTP rules of ARCHITECTURE §13.9: 6 random digits (OTP_FAKE_CODE in local and testing),
 * 10-minute lifetime, 5 attempts per code, a 60-second resend cooldown and 5 codes per hour per
 * e-mail. Only the HMAC of the code is stored.
 */
final class OtpService implements OtpCodes
{
    private const string INVALID = 'otp_invalid';

    private const string EXPIRED = 'otp_expired';

    private const string TOO_MANY_ATTEMPTS = 'otp_too_many_attempts';

    public function send(
        string $email,
        OtpPurpose $purpose,
        ?User $user = null,
        array $context = [],
        ?string $locale = null,
        ?string $ip = null,
    ): OtpCode {
        $email = self::normalise($email);

        return DB::transaction(function () use ($email, $purpose, $user, $context, $locale, $ip): OtpCode {
            // Serialises concurrent sends to the same address (cooldown and hourly cap).
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['otp:'.$email]);

            $now = CarbonImmutable::instance(Date::now());
            $this->assertCanSend($email, $purpose, $now);

            OtpCode::query()
                ->where('email', $email)
                ->where('purpose', $purpose->value)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => $now]);

            $code = $this->generateCode();

            $otp = OtpCode::query()->create([
                'email' => $email,
                'user_id' => $user?->id,
                'purpose' => $purpose,
                'code_hash' => OtpCode::hashCode($code),
                'context' => $context === [] ? null : $context,
                'attempts' => 0,
                'expires_at' => $now->addMinutes($this->ttlMinutes()),
                'ip' => $ip,
            ]);

            Mail::to($email)->queue(
                (new OtpCodeMail($code, $purpose, $this->ttlMinutes()))->locale($locale ?? $user->locale ?? App::getLocale()),
            );

            return $otp;
        });
    }

    /**
     * Sends a code unless one was sent within the cooldown, in which case that code stays valid
     * and is returned (the login of an unverified user, §13.9). Null when the hourly cap is hit.
     */
    public function sendRespectingCooldown(
        string $email,
        OtpPurpose $purpose,
        ?User $user = null,
        ?string $locale = null,
        ?string $ip = null,
    ): ?OtpCode {
        try {
            return $this->send($email, $purpose, $user, [], $locale, $ip);
        } catch (ApiException $e) {
            if ($e->errorCode !== 'otp_resend_cooldown') {
                throw $e;
            }

            return $this->latestUsable($email, $purpose);
        }
    }

    /**
     * The latest unconsumed, unexpired code of this e-mail and purpose.
     */
    public function latestUsable(string $email, OtpPurpose $purpose): ?OtpCode
    {
        return OtpCode::query()
            ->where('email', self::normalise($email))
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', Date::now())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    public function check(string $email, OtpPurpose $purpose, string $code): OtpCode
    {
        return $this->attempt($email, $purpose, $code, consume: false);
    }

    public function consume(string $email, OtpPurpose $purpose, string $code): OtpCode
    {
        return $this->attempt($email, $purpose, $code, consume: true);
    }

    /**
     * Compares the code with the latest code of the e-mail and purpose, under a row lock. The
     * attempt is committed before any error is thrown, so wrong codes always count.
     */
    private function attempt(string $email, OtpPurpose $purpose, string $code, bool $consume): OtpCode
    {
        $email = self::normalise($email);

        /** @var array{0: OtpCode|null, 1: string|null} $result */
        $result = DB::transaction(function () use ($email, $purpose, $code, $consume): array {
            $otp = OtpCode::query()
                ->where('email', $email)
                ->where('purpose', $purpose->value)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            // No code at all for this address: the same answer as a wrong code (never reveal
            // whether an account exists).
            if ($otp === null) {
                return [null, self::INVALID];
            }

            $now = Date::now();

            if ($otp->consumed_at !== null || $otp->expires_at->lessThanOrEqualTo($now)) {
                return [$otp, self::EXPIRED];
            }

            if (! hash_equals($otp->code_hash, OtpCode::hashCode($code))) {
                $otp->attempts++;

                if ($otp->attempts >= $this->maxAttempts()) {
                    $otp->consumed_at = CarbonImmutable::instance($now);
                    $otp->save();

                    return [$otp, self::TOO_MANY_ATTEMPTS];
                }

                $otp->save();

                return [$otp, self::INVALID];
            }

            if ($consume) {
                $otp->consumed_at = CarbonImmutable::instance($now);
                $otp->save();
            }

            return [$otp, null];
        });

        [$otp, $error] = $result;

        if ($error !== null || $otp === null) {
            throw new ApiException(
                errorCode: $error ?? self::INVALID,
                messageKey: 'identity.errors.'.($error ?? self::INVALID),
                status: $error === self::TOO_MANY_ATTEMPTS ? 429 : 422,
            );
        }

        return $otp;
    }

    /**
     * 60 s since the last code of this purpose, and at most 5 codes per hour to this address
     * (any purpose). Both answer 429 `otp_resend_cooldown` with the wait in seconds.
     */
    private function assertCanSend(string $email, OtpPurpose $purpose, CarbonImmutable $now): void
    {
        $latest = OtpCode::query()
            ->where('email', $email)
            ->where('purpose', $purpose->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $cooldownEndsAt = $latest?->created_at?->addSeconds($this->cooldownSeconds());

        if ($cooldownEndsAt !== null && $cooldownEndsAt->greaterThan($now)) {
            throw $this->cooldown($cooldownEndsAt->getTimestamp() - $now->getTimestamp());
        }

        $hourAgo = $now->subHour();
        $sentInLastHour = OtpCode::query()->where('email', $email)->where('created_at', '>', $hourAgo);

        if ($sentInLastHour->count() >= $this->maxSendsPerHour()) {
            // CONTRACT-GAP: §13.9 gives the hourly cap but no error of its own; it reuses
            // `otp_resend_cooldown`, with the wait until the oldest code of the hour ages out.
            $oldest = $sentInLastHour->min('created_at');
            $oldestAt = is_string($oldest) ? CarbonImmutable::parse($oldest) : $hourAgo;

            throw $this->cooldown($oldestAt->addHour()->getTimestamp() - $now->getTimestamp());
        }
    }

    private function cooldown(int $seconds): ApiException
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

    private function generateCode(): string
    {
        $fake = config('bafo.identity.otp.fake_code');

        if (is_scalar($fake) && preg_match('/^\d{6}$/', (string) $fake) === 1 && App::environment(['local', 'testing'])) {
            return (string) $fake;
        }

        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }

    private function ttlMinutes(): int
    {
        return self::intConfig('ttl_minutes', 10);
    }

    private function maxAttempts(): int
    {
        return self::intConfig('max_attempts', 5);
    }

    private function cooldownSeconds(): int
    {
        return self::intConfig('resend_cooldown_seconds', 60);
    }

    private function maxSendsPerHour(): int
    {
        return self::intConfig('max_sends_per_hour', 5);
    }

    private static function intConfig(string $key, int $default): int
    {
        $value = config('bafo.identity.otp.'.$key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    private static function normalise(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
