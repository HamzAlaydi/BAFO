<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Mail\OtpCodeMail;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLog;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\Identity\Accounts;

beforeEach(function () {
    Mail::fake();
});

function identityLogin(string $email, string $password = Accounts::PASSWORD, array $headers = []): TestResponse
{
    return test()->postJson('/api/app/v1/auth/login', ['email' => $email, 'password' => $password, 'device_name' => 'iPhone 16 · ios'], $headers);
}

it('registers the Identity endpoints of API.md §1.3', function (string $name, string $method, string $uri, bool $guest) {
    $route = app(Router::class)->getRoutes()->getByName($name);

    expect($route)->not->toBeNull()
        ->and($route?->uri())->toBe($uri)
        ->and($route?->methods())->toContain($method)
        ->and(in_array('auth:sanctum', $route?->excludedMiddleware() ?? [], true))->toBe($guest);

    if ($guest) {
        expect($route?->middleware())->toContain('throttle:auth');
    }
})->with([
    ['app.v1.auth.register', 'POST', 'api/app/v1/auth/register', true],
    ['app.v1.auth.otp.send', 'POST', 'api/app/v1/auth/otp/send', true],
    ['app.v1.auth.otp.verify', 'POST', 'api/app/v1/auth/otp/verify', true],
    ['app.v1.auth.otp.check', 'POST', 'api/app/v1/auth/otp/check', true],
    ['app.v1.auth.login', 'POST', 'api/app/v1/auth/login', true],
    ['app.v1.auth.logout', 'POST', 'api/app/v1/auth/logout', false],
    ['app.v1.auth.password.forgot', 'POST', 'api/app/v1/auth/password/forgot', true],
    ['app.v1.auth.password.reset', 'POST', 'api/app/v1/auth/password/reset', true],
    ['app.v1.auth.team-invitations.lookup', 'POST', 'api/app/v1/auth/team-invitations/lookup', true],
    ['app.v1.auth.team-invitations.accept', 'POST', 'api/app/v1/auth/team-invitations/accept', true],
    ['app.v1.me.show', 'GET', 'api/app/v1/me', false],
    ['app.v1.me.update', 'PATCH', 'api/app/v1/me', false],
    ['app.v1.me.password', 'PUT', 'api/app/v1/me/password', false],
    ['app.v1.me.avatar.store', 'POST', 'api/app/v1/me/avatar', false],
    ['app.v1.me.avatar.destroy', 'DELETE', 'api/app/v1/me/avatar', false],
    ['app.v1.organization.show', 'GET', 'api/app/v1/organization', false],
    ['app.v1.organization.update', 'PATCH', 'api/app/v1/organization', false],
    ['app.v1.organization.logo.store', 'POST', 'api/app/v1/organization/logo', false],
    ['app.v1.organization.logo.destroy', 'DELETE', 'api/app/v1/organization/logo', false],
    ['app.v1.organization.profile-document.store', 'POST', 'api/app/v1/organization/profile-document', false],
    ['app.v1.organization.profile-document.destroy', 'DELETE', 'api/app/v1/organization/profile-document', false],
    ['app.v1.team.members.index', 'GET', 'api/app/v1/team/members', false],
    ['app.v1.team.members.store', 'POST', 'api/app/v1/team/members', false],
    ['app.v1.team.members.update', 'PATCH', 'api/app/v1/team/members/{membership}', false],
    ['app.v1.team.members.destroy', 'DELETE', 'api/app/v1/team/members/{membership}', false],
    ['app.v1.team.members.resend', 'POST', 'api/app/v1/team/members/{membership}/resend-invitation', false],
    ['app.v1.account.deletion.store', 'POST', 'api/app/v1/account/deletion', false],
    ['app.v1.account.deletion.show', 'GET', 'api/app/v1/account/deletion', false],
    ['app.v1.account.deletion.destroy', 'DELETE', 'api/app/v1/account/deletion', false],
]);

it('signs in with e-mail and password and returns the AuthTokenPayload', function () {
    $user = Accounts::owner();

    $response = identityLogin(strtoupper($user->email))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email', 'phone', 'locale', 'avatar_url', 'status', 'email_verified_at', 'created_at'],
                'organization' => ['id', 'name', 'cr_number', 'region' => ['id', 'code', 'name']],
                'membership' => ['id', 'role', 'can_award', 'can_purchase', 'status'],
                'permissions',
                'subscription',
                'entitlements' => ['can_issue', 'seats_used', 'seats_total'],
                'unread_notifications_count',
                'token',
                'token_type',
            ],
        ])
        ->assertJsonPath('data.user.id', $user->public_id)
        ->assertJsonPath('data.token_type', 'Bearer');

    expect(PersonalAccessToken::query()->sole())
        ->name->toBe('iPhone 16 · ios')
        ->tokenable_type->toBe('user')
        ->and($user->refresh()->last_login_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'user.signed_in')->sole()->subject_public_id)->toBe($user->public_id);

    $this->getJson('/api/app/v1/me', Accounts::bearer($response->json('data.token')))->assertOk();
});

it('never says which credential was wrong', function (string $email, string $password) {
    Accounts::owner(null)->forceFill(['email' => 'known@acme.sa'])->save();

    identityLogin($email, $password, ['Accept-Language' => 'en'])
        ->assertUnauthorized()
        ->assertExactJson([
            'message' => 'The e-mail address or password is incorrect.',
            'code' => 'invalid_credentials',
            'errors' => [],
        ]);
})->with([
    'unknown e-mail' => ['nobody@acme.sa', Accounts::PASSWORD],
    'wrong password' => ['known@acme.sa', 'Wrong-Passw0rd!'],
]);

it('refuses a deleted user and an invited user without a password', function () {
    $organization = Organization::factory()->create();
    $deleted = Accounts::member($organization);
    $deleted->forceFill(['status' => UserStatus::Deleted])->save();
    $deleted->delete();
    User::factory()->invited()->withMembership($organization)->create(['email' => 'invited@acme.sa']);

    identityLogin($deleted->email)->assertUnauthorized()->assertJsonPath('code', 'invalid_credentials');
    identityLogin('invited@acme.sa')->assertUnauthorized()->assertJsonPath('code', 'invalid_credentials');
});

it('refuses an inactive membership', function () {
    $user = Accounts::member(Organization::factory()->create());
    $user->membership->forceFill(['status' => MembershipStatus::Inactive])->save();

    identityLogin($user->email)->assertForbidden()->assertJsonPath('code', 'account_inactive');
    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('refuses a suspended organization', function () {
    $user = Accounts::owner(Organization::factory()->suspended()->create());

    identityLogin($user->email)->assertForbidden()->assertJsonPath('code', 'organization_suspended');
});

it('sends a new verification code to an unverified user', function () {
    $user = User::factory()->unverified()->withMembership(Organization::factory()->create(), OrgRole::Owner)
        ->create(['password' => Accounts::PASSWORD]);

    $response = identityLogin($user->email)
        ->assertForbidden()
        ->assertJsonPath('code', 'email_not_verified');

    expect($response->json('details.otp_expires_at'))->toBeIso8601Utc()
        ->and(PersonalAccessToken::query()->count())->toBe(0);
    Mail::assertQueued(OtpCodeMail::class, 1);

    // Within the cooldown: no second mail, the same code stays valid.
    $again = identityLogin($user->email)->assertForbidden();
    expect($again->json('details.otp_expires_at'))->toBe($response->json('details.otp_expires_at'))
        ->and(OtpCode::query()->count())->toBe(1);
    Mail::assertQueued(OtpCodeMail::class, 1);
});

it('validates the input', function () {
    $this->postJson('/api/app/v1/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password', 'device_name']);
});

it('signs out by deleting the current token only', function () {
    $user = Accounts::owner();
    $other = $user->createToken('other device')->plainTextToken;
    $headers = Accounts::headers($user);

    $this->postJson('/api/app/v1/auth/logout', [], $headers)->assertNoContent();

    expect(PersonalAccessToken::query()->pluck('name')->all())->toBe(['other device']);

    $this->getJson('/api/app/v1/me', Accounts::bearer(substr($headers['Authorization'], 7)))->assertUnauthorized();
    $this->getJson('/api/app/v1/me', Accounts::bearer($other))->assertOk();
});

it('requires a token to sign out', function () {
    $this->postJson('/api/app/v1/auth/logout')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});
