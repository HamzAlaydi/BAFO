<?php

declare(strict_types=1);

use App\Modules\Identity\Exceptions\IdentityError;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

uses(TestCase::class);

it('uses the one status of each CONVENTIONS §8.3 code', function (string $code, int $status) {
    $exception = IdentityError::make($code, ['x' => 1]);

    expect($exception->errorCode)->toBe($code)
        ->and($exception->status)->toBe($status)
        ->and($exception->messageKey)->toBe('identity.errors.'.$code)
        ->and($exception->details)->toBe(['x' => 1]);
})->with([
    ['invalid_credentials', 401],
    ['email_not_verified', 403],
    ['account_inactive', 403],
    ['organization_suspended', 403],
    ['otp_invalid', 422],
    ['otp_expired', 422],
    ['otp_too_many_attempts', 429],
    ['otp_resend_cooldown', 429],
    ['password_incorrect', 422],
    ['team_invitation_invalid', 422],
    ['seat_limit_reached', 409],
    ['cannot_modify_owner', 409],
    ['cannot_modify_self', 409],
    ['account_deletion_blocked', 409],
    ['account_deletion_pending', 409],
    ['invitation_email_mismatch', 422],
]);

it('has an Arabic and an English message for every code', function () {
    foreach (array_keys(IdentityError::STATUS) as $code) {
        expect(Lang::has('identity.errors.'.$code, 'ar', false))->toBeTrue("missing ar {$code}")
            ->and(Lang::has('identity.errors.'.$code, 'en', false))->toBeTrue("missing en {$code}");
    }
});

it('never uses an exclamation mark or the old brand name in its copy', function () {
    foreach (['ar', 'en'] as $locale) {
        $text = json_encode(require lang_path("{$locale}/identity.php"), JSON_UNESCAPED_UNICODE);

        expect($text)->not->toContain('!')
            ->and(mb_strtolower((string) $text))->not->toContain('munaqes')
            ->and((string) $text)->not->toContain('مناقص ');
    }
});
