<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Mail\OtpCodeMail;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Support\Identity\Accounts;

/*
 * Security review (docs/build/SECURITY_REVIEW.md) S-01: the guest identity endpoints never tell
 * an unknown e-mail from a registered one: not in the body, the status, nor the rate limits.
 */

beforeEach(function () {
    Mail::fake();
});

/**
 * The status, the error code and the body keys, without values that differ per call.
 *
 * @return array{status: int, code: mixed, keys: list<string>, expires_is_set: bool}
 */
function securityShape(TestResponse $response): array
{
    return [
        'status' => $response->status(),
        'code' => $response->json('code'),
        'keys' => array_keys((array) $response->json('data')),
        'expires_is_set' => is_string($response->json('data.otp_expires_at')),
    ];
}

function securitySendOtp(object $test, string $email, string $purpose): TestResponse
{
    // A fresh client IP per call: the per-IP `auth` limiter is not what these tests are about.
    return $test->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.random_int(1, 250)])
        ->postJson('/api/app/v1/auth/otp/send', ['email' => $email, 'purpose' => $purpose]);
}

it('answers otp/send for an unknown e-mail exactly like for a registered one', function (string $purpose) {
    $organization = Organization::factory()->create();
    $known = $purpose === 'password_reset'
        ? Accounts::owner()->email
        : User::factory()->unverified()->withMembership($organization, OrgRole::Owner)->create(['email' => 'new@acme.sa', 'password' => Accounts::PASSWORD])->email;

    // First call: 202 with an expiry for both.
    $first = [securityShape(securitySendOtp($this, $known, $purpose)), securityShape(securitySendOtp($this, 'nobody@acme.sa', $purpose))];
    expect($first[0])->toBe($first[1])
        ->and($first[0]['status'])->toBe(202)
        ->and($first[0]['expires_is_set'])->toBeTrue();

    // Within the cooldown: 429 otp_resend_cooldown for both.
    $this->travel(10)->seconds();
    $second = [securityShape(securitySendOtp($this, $known, $purpose)), securityShape(securitySendOtp($this, 'nobody@acme.sa', $purpose))];
    expect($second[0])->toBe($second[1])
        ->and($second[0]['status'])->toBe(429)
        ->and($second[0]['code'])->toBe('otp_resend_cooldown');

    // After it: 202 again for both.
    $this->travel(61)->seconds();
    expect(securityShape(securitySendOtp($this, 'nobody@acme.sa', $purpose)))->toBe(securityShape(securitySendOtp($this, $known, $purpose)));

    // Only the registered address received codes; the unknown one left no OTP row.
    Mail::assertNotQueued(OtpCodeMail::class, fn (OtpCodeMail $mail): bool => $mail->hasTo('nobody@acme.sa'));
    Mail::assertQueued(OtpCodeMail::class, fn (OtpCodeMail $mail): bool => $mail->hasTo($known));
    expect(OtpCode::query()->where('email', 'nobody@acme.sa')->exists())->toBeFalse();
})->with(['password_reset', 'email_verification']);

it('applies the hourly cap to unknown e-mails as to real ones', function () {
    foreach (range(1, 5) as $ignored) {
        securitySendOtp($this, 'nobody@acme.sa', 'password_reset')->assertStatus(202);
        $this->travel(61)->seconds();
    }

    securitySendOtp($this, 'nobody@acme.sa', 'password_reset')
        ->assertStatus(429)
        ->assertJsonPath('code', 'otp_resend_cooldown');

    $this->travel(1)->hours();
    securitySendOtp($this, 'nobody@acme.sa', 'password_reset')->assertStatus(202);
});

it('answers password/forgot with the same 202 for unknown and registered e-mails', function () {
    $known = Accounts::owner()->email;

    $registered = $this->postJson('/api/app/v1/auth/password/forgot', ['email' => $known]);
    $unknown = $this->postJson('/api/app/v1/auth/password/forgot', ['email' => 'nobody@acme.sa']);

    expect([$registered->status(), $registered->json('data')])->toBe([202, []])
        ->and([$unknown->status(), $unknown->json('data')])->toBe([202, []]);

    // Within the cooldown too: the registered address does not start answering 429.
    $this->postJson('/api/app/v1/auth/password/forgot', ['email' => $known])->assertStatus(202);
});

it('answers login with the same invalid_credentials for an unknown e-mail and a wrong password', function () {
    $known = Accounts::owner()->email;

    $unknown = $this->postJson('/api/app/v1/auth/login', ['email' => 'nobody@acme.sa', 'password' => 'Wr0ng-Passw0rd!', 'device_name' => 'x']);
    $wrong = $this->postJson('/api/app/v1/auth/login', ['email' => $known, 'password' => 'Wr0ng-Passw0rd!', 'device_name' => 'x']);

    expect([$unknown->status(), $unknown->json('code')])->toBe([$wrong->status(), $wrong->json('code')])
        ->and($unknown->json('code'))->toBe('invalid_credentials');
});
