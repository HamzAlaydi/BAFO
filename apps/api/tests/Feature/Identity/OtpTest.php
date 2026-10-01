<?php

declare(strict_types=1);

use App\Modules\Identity\Contracts\OtpCodes;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Events\EmailVerified;
use App\Modules\Identity\Mail\OtpCodeMail;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\Identity\Accounts;

beforeEach(function () {
    Mail::fake();
});

function identityUnverifiedOwner(string $email = 'new@acme.sa'): User
{
    $organization = Organization::factory()->create();

    return User::factory()->unverified()->withMembership($organization, OrgRole::Owner)
        ->create(['email' => $email, 'password' => Accounts::PASSWORD]);
}

function identitySendCode(User $user, OtpPurpose $purpose = OtpPurpose::EmailVerification): OtpCode
{
    return app(OtpCodes::class)->send($user->email, $purpose, $user);
}

describe('send', function () {
    it('sends a verification code to an unverified user', function () {
        $user = identityUnverifiedOwner();

        $response = $this->postJson('/api/app/v1/auth/otp/send', ['email' => 'NEW@acme.sa', 'purpose' => 'email_verification'])
            ->assertStatus(202)
            ->assertJsonStructure(['data' => ['otp_expires_at'], 'meta' => ['server_time']]);

        expect($response->json('data.otp_expires_at'))->toBeIso8601Utc();
        Mail::assertQueued(OtpCodeMail::class, fn (OtpCodeMail $mail): bool => $mail->hasTo($user->email) && $mail->code === '123456');
    });

    it('answers 202 like for a real account and sends nothing for unknown e-mails', function () {
        $this->postJson('/api/app/v1/auth/otp/send', ['email' => 'nobody@acme.sa', 'purpose' => 'password_reset'])
            ->assertStatus(202)
            ->assertJsonStructure(['data' => ['otp_expires_at']]); // SECURITY_REVIEW S-01: same answer as a real account

        Mail::assertNothingQueued();
        expect(OtpCode::query()->count())->toBe(0);
    });

    it('sends no verification code to a verified user', function () {
        $user = Accounts::owner();

        $this->postJson('/api/app/v1/auth/otp/send', ['email' => $user->email, 'purpose' => 'email_verification'])
            ->assertStatus(202)
            ->assertJsonStructure(['data' => ['otp_expires_at']]); // SECURITY_REVIEW S-01: same answer as a real account

        Mail::assertNothingQueued();
    });

    it('sends a password reset code to an existing user', function () {
        $user = Accounts::owner();

        $this->postJson('/api/app/v1/auth/otp/send', ['email' => $user->email, 'purpose' => 'password_reset'])->assertStatus(202);

        Mail::assertQueued(OtpCodeMail::class, fn (OtpCodeMail $mail): bool => $mail->purpose === OtpPurpose::PasswordReset);
    });

    it('enforces the 60 second resend cooldown', function () {
        $user = identityUnverifiedOwner();
        identitySendCode($user);

        $this->travel(20)->seconds();

        $this->postJson('/api/app/v1/auth/otp/send', ['email' => $user->email, 'purpose' => 'email_verification'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'otp_resend_cooldown')
            ->assertJsonPath('details.retry_after_seconds', 40)
            ->assertHeader('Retry-After', '40');

        $this->travel(41)->seconds();

        $this->postJson('/api/app/v1/auth/otp/send', ['email' => $user->email, 'purpose' => 'email_verification'])->assertStatus(202);
    });

    it('caps the codes at 5 per hour per e-mail', function () {
        $user = identityUnverifiedOwner();

        foreach (range(1, 5) as $i) {
            identitySendCode($user);
            $this->travel(61)->seconds();
        }

        expect(fn () => identitySendCode($user))->toThrow(ApiException::class, 'otp_resend_cooldown');

        $this->travel(1)->hours();
        expect(identitySendCode($user))->toBeInstanceOf(OtpCode::class);
    });

    it('invalidates the older codes when a new one is sent', function () {
        $user = identityUnverifiedOwner();
        $first = identitySendCode($user);
        $this->travel(61)->seconds();
        identitySendCode($user);

        expect($first->refresh()->consumed_at)->not->toBeNull()
            ->and(OtpCode::query()->whereNull('consumed_at')->count())->toBe(1);
    });

    it('validates the purpose', function () {
        $this->postJson('/api/app/v1/auth/otp/send', ['email' => 'not-an-email', 'purpose' => 'invitation_claim'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'purpose']);
    });
});

describe('verify', function () {
    it('verifies the e-mail and signs the user in', function () {
        Event::fake([EmailVerified::class]);
        $user = identityUnverifiedOwner();
        identitySendCode($user);

        $response = $this->postJson('/api/app/v1/auth/otp/verify', [
            'email' => $user->email,
            'code' => '123456',
            'purpose' => 'email_verification',
            'device_name' => 'Pixel 8 · android',
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['user', 'organization', 'membership', 'permissions', 'subscription', 'entitlements', 'unread_notifications_count', 'token', 'token_type']])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.status', 'active')
            ->assertJsonPath('data.membership.role', 'owner');

        $user->refresh();

        expect($user->status)->toBe(UserStatus::Active)
            ->and($user->email_verified_at)->not->toBeNull()
            ->and($user->last_login_at)->not->toBeNull()
            ->and(PersonalAccessToken::query()->sole()->name)->toBe('Pixel 8 · android')
            ->and(OtpCode::query()->sole()->consumed_at)->not->toBeNull()
            ->and(AuditLog::query()->where('action', 'user.email_verified')->exists())->toBeTrue();

        Event::assertDispatched(EmailVerified::class, fn (EmailVerified $event): bool => $event->user->is($user));

        $this->getJson('/api/app/v1/me', Accounts::bearer($response->json('data.token')))
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->public_id);
    });

    it('rejects a wrong code and counts the attempt', function () {
        $user = identityUnverifiedOwner();
        identitySendCode($user);

        $this->postJson('/api/app/v1/auth/otp/verify', ['email' => $user->email, 'code' => '000000', 'device_name' => 'web'], ['Accept-Language' => 'en'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'otp_invalid')
            ->assertJsonPath('message', 'The code is incorrect.');

        expect(OtpCode::query()->sole()->attempts)->toBe(1)
            ->and($user->refresh()->email_verified_at)->toBeNull();
    });

    it('consumes the code after 5 wrong attempts', function () {
        $user = identityUnverifiedOwner();
        identitySendCode($user);

        foreach (range(1, 4) as $i) {
            expect(fn () => app(OtpCodes::class)->consume($user->email, OtpPurpose::EmailVerification, '000000'))
                ->toThrow(ApiException::class, 'otp_invalid');
        }

        $this->postJson('/api/app/v1/auth/otp/verify', ['email' => $user->email, 'code' => '000000', 'device_name' => 'web'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'otp_too_many_attempts');

        $this->postJson('/api/app/v1/auth/otp/verify', ['email' => $user->email, 'code' => '123456', 'device_name' => 'web'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'otp_expired');
    });

    it('rejects an expired code', function () {
        $user = identityUnverifiedOwner();
        identitySendCode($user);
        $this->travel(11)->minutes();

        $this->postJson('/api/app/v1/auth/otp/verify', ['email' => $user->email, 'code' => '123456', 'device_name' => 'web'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'otp_expired');
    });

    it('rejects a code that was already used', function () {
        $user = identityUnverifiedOwner();
        identitySendCode($user);

        $this->postJson('/api/app/v1/auth/otp/verify', ['email' => $user->email, 'code' => '123456', 'device_name' => 'web'])->assertOk();

        $this->postJson('/api/app/v1/auth/otp/verify', ['email' => $user->email, 'code' => '123456', 'device_name' => 'web'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'otp_expired');
    });

    it('answers otp_invalid when no code was ever sent', function () {
        $this->postJson('/api/app/v1/auth/otp/verify', ['email' => 'nobody@acme.sa', 'code' => '123456', 'device_name' => 'web'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'otp_invalid');
    });

    it('does not accept a password reset code for verification', function () {
        $user = identityUnverifiedOwner();
        identitySendCode($user, OtpPurpose::PasswordReset);

        $this->postJson('/api/app/v1/auth/otp/verify', ['email' => $user->email, 'code' => '123456', 'device_name' => 'web'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'otp_invalid');
    });

    it('validates the input', function () {
        $this->postJson('/api/app/v1/auth/otp/verify', ['email' => 'a@b.sa', 'code' => '12ab', 'purpose' => 'password_reset'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'purpose', 'device_name']);
    });
});

describe('check', function () {
    it('checks a password reset code without consuming it', function () {
        $user = Accounts::owner();
        identitySendCode($user, OtpPurpose::PasswordReset);

        $this->postJson('/api/app/v1/auth/otp/check', ['email' => $user->email, 'code' => '123456', 'purpose' => 'password_reset'])
            ->assertOk()
            ->assertJsonPath('data', ['valid' => true]);

        expect(OtpCode::query()->sole()->consumed_at)->toBeNull();
    });

    it('rejects a wrong code', function () {
        $user = Accounts::owner();
        identitySendCode($user, OtpPurpose::PasswordReset);

        $this->postJson('/api/app/v1/auth/otp/check', ['email' => $user->email, 'code' => '654321', 'purpose' => 'password_reset'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'otp_invalid');
    });

    it('only checks password reset codes', function () {
        $this->postJson('/api/app/v1/auth/otp/check', ['email' => 'a@b.sa', 'code' => '123456', 'purpose' => 'email_verification'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['purpose']);
    });
});

it('generates random codes outside local and testing', function () {
    $this->app->detectEnvironment(fn () => 'production');
    $user = identityUnverifiedOwner();

    identitySendCode($user);

    $mail = Mail::queued(OtpCodeMail::class)->sole();
    expect($mail->code)->toMatch('/^\d{6}$/')
        ->and(OtpCode::query()->sole()->code_hash)->toBe(OtpCode::hashCode($mail->code));
});

it('stores only the HMAC of the code', function () {
    $user = identityUnverifiedOwner();
    identitySendCode($user);

    $row = OtpCode::query()->sole();

    expect($row->code_hash)->not->toContain('123456')
        ->and($row->toArray())->not->toHaveKey('code_hash');
});

it('sends no code to a team member who has not accepted the invitation', function () {
    Membership::factory()->invited()->for(User::factory()->invited()->create(['email' => 'pending@acme.sa']))->create();

    foreach (['email_verification', 'password_reset'] as $purpose) {
        $this->postJson('/api/app/v1/auth/otp/send', ['email' => 'pending@acme.sa', 'purpose' => $purpose])
            ->assertStatus(202)
            ->assertJsonStructure(['data' => ['otp_expires_at']]); // SECURITY_REVIEW S-01: same answer as a real account
    }

    $this->postJson('/api/app/v1/auth/password/forgot', ['email' => 'pending@acme.sa'])->assertStatus(202);

    Mail::assertNothingQueued();
});
