<?php

declare(strict_types=1);

use App\Modules\Identity\Contracts\OtpCodes;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\Identity\Accounts;

/*
 * Security review (docs/build/SECURITY_REVIEW.md): one-time secrets work once. A team invitation
 * link cannot be replayed (for instance from a forwarded mail) to take over the account after it
 * was accepted, and a password reset code cannot be used twice; sign-out revokes the token.
 */

beforeEach(function () {
    Mail::fake();
    Accounts::publishLegalDocuments();
});

it('accepts a team invitation link once only', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->invited()->create(['email' => 'mona@acme.sa', 'status' => UserStatus::PendingVerification]);
    Membership::factory()->invited('one-time-token')->for($organization)->for($user)->role(OrgRole::Member)->create();

    $accept = fn (string $password) => $this->postJson('/api/app/v1/auth/team-invitations/accept', [
        'token' => 'one-time-token', 'password' => $password, 'password_confirmation' => $password,
        'device_name' => 'Chrome · web', 'accept_terms' => true,
    ]);

    $accept(Accounts::PASSWORD)->assertOk();

    // A replay (a forwarded mail) cannot reset the password of the now active account.
    $accept(Accounts::NEW_PASSWORD)->assertUnprocessable()->assertJsonPath('code', 'team_invitation_invalid');
    $this->postJson('/api/app/v1/auth/team-invitations/lookup', ['token' => 'one-time-token'])
        ->assertUnprocessable()->assertJsonPath('code', 'team_invitation_invalid');

    $this->postJson('/api/app/v1/auth/login', ['email' => 'mona@acme.sa', 'password' => Accounts::PASSWORD, 'device_name' => 'web'])->assertOk();
});

it('uses a password reset code once only', function () {
    $user = Accounts::owner();
    app(OtpCodes::class)->send($user->email, OtpPurpose::PasswordReset, $user);

    $reset = fn (string $password) => $this->postJson('/api/app/v1/auth/password/reset', [
        'email' => $user->email, 'code' => '123456', 'password' => $password, 'password_confirmation' => $password,
    ]);

    $reset(Accounts::NEW_PASSWORD)->assertNoContent();
    $reset('An0ther-Passw0rd!')->assertUnprocessable();

    $this->postJson('/api/app/v1/auth/login', ['email' => $user->email, 'password' => Accounts::NEW_PASSWORD, 'device_name' => 'web'])->assertOk();
});

it('revokes the bearer token at sign-out', function () {
    $user = Accounts::owner();
    $headers = Accounts::headers($user);

    $this->getJson('/api/app/v1/me', $headers)->assertOk();
    $this->postJson('/api/app/v1/auth/logout', [], $headers)->assertNoContent();

    expect(PersonalAccessToken::query()->count())->toBe(0);
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/app/v1/me', $headers)->assertUnauthorized();
});
