<?php

declare(strict_types=1);

use App\Modules\Identity\Contracts\OtpCodes;
use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Mail\OtpCodeMail;
use App\Modules\Identity\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\Identity\Accounts;

beforeEach(function () {
    Mail::fake();
});

it('sends a reset code to an existing user and always answers 202', function () {
    $user = Accounts::owner();

    $this->postJson('/api/app/v1/auth/password/forgot', ['email' => strtoupper($user->email)])
        ->assertStatus(202)
        ->assertJsonPath('data', []);

    Mail::assertQueued(OtpCodeMail::class, fn (OtpCodeMail $mail): bool => $mail->hasTo($user->email) && $mail->purpose === OtpPurpose::PasswordReset);
});

it('answers 202 for an unknown e-mail without sending anything', function () {
    $this->postJson('/api/app/v1/auth/password/forgot', ['email' => 'nobody@acme.sa'])->assertStatus(202);

    Mail::assertNothingQueued();
});

it('answers 202 within the resend cooldown too', function () {
    $user = Accounts::owner();

    $this->postJson('/api/app/v1/auth/password/forgot', ['email' => $user->email])->assertStatus(202);
    $this->postJson('/api/app/v1/auth/password/forgot', ['email' => $user->email])->assertStatus(202);

    expect(OtpCode::query()->count())->toBe(1);
});

it('resets the password and revokes every token', function () {
    $user = Accounts::owner();
    $user->createToken('web');
    $user->createToken('android');
    app(OtpCodes::class)->send($user->email, OtpPurpose::PasswordReset, $user);

    $this->postJson('/api/app/v1/auth/password/reset', [
        'email' => $user->email,
        'code' => '123456',
        'password' => Accounts::NEW_PASSWORD,
        'password_confirmation' => Accounts::NEW_PASSWORD,
    ])->assertNoContent();

    expect(Hash::check(Accounts::NEW_PASSWORD, (string) $user->refresh()->password))->toBeTrue()
        ->and(PersonalAccessToken::query()->count())->toBe(0)
        ->and(OtpCode::query()->sole()->consumed_at)->not->toBeNull();

    $this->postJson('/api/app/v1/auth/login', ['email' => $user->email, 'password' => Accounts::NEW_PASSWORD, 'device_name' => 'web'])->assertOk();
});

it('rejects a wrong or expired code', function () {
    $user = Accounts::owner();
    app(OtpCodes::class)->send($user->email, OtpPurpose::PasswordReset, $user);
    $payload = ['email' => $user->email, 'password' => Accounts::NEW_PASSWORD, 'password_confirmation' => Accounts::NEW_PASSWORD];

    $this->postJson('/api/app/v1/auth/password/reset', [...$payload, 'code' => '111111'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp_invalid');

    $this->travel(11)->minutes();

    $this->postJson('/api/app/v1/auth/password/reset', [...$payload, 'code' => '123456'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp_expired');

    expect(Hash::check(Accounts::PASSWORD, (string) $user->refresh()->password))->toBeTrue();
});

it('enforces the password rule', function () {
    $this->postJson('/api/app/v1/auth/password/reset', [
        'email' => 'a@b.sa',
        'code' => '123456',
        'password' => 'alllowercase1!',
        'password_confirmation' => 'alllowercase1!',
    ], ['Accept-Language' => 'en'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});
