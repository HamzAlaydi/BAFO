<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Http\Middleware\EnsureAccountActive;
use App\Modules\Identity\Models\Organization;
use Illuminate\Routing\Router;
use Tests\Support\Identity\Accounts;

it('is appended to the app_v1 group', function () {
    expect(app(Router::class)->getMiddlewareGroups()['app_v1'])->toContain(EnsureAccountActive::class);
});

it('lets an active account through', function () {
    $this->getJson('/api/app/v1/organization', Accounts::headers(Accounts::owner()))->assertOk();
});

it('blocks an inactive membership but leaves /me, logout and account deletion open', function () {
    $user = Accounts::member(Organization::factory()->create());
    $user->membership->forceFill(['status' => MembershipStatus::Inactive])->save();
    $headers = Accounts::headers($user);

    $this->getJson('/api/app/v1/organization', $headers)
        ->assertForbidden()
        ->assertJsonPath('code', 'account_inactive')
        ->assertJsonPath('message', __('identity.errors.account_inactive'));

    $this->getJson('/api/app/v1/me', $headers)
        ->assertOk()
        ->assertJsonPath('data.membership.status', 'inactive')
        ->assertJsonPath('data.permissions', []);

    $this->getJson('/api/app/v1/account/deletion', $headers)->assertOk();
    $this->postJson('/api/app/v1/auth/logout', [], $headers)->assertNoContent();
});

it('blocks a suspended organization', function () {
    $user = Accounts::owner();
    $user->membership->organization->forceFill(['status' => OrganizationStatus::Suspended, 'suspended_at' => now()])->save();

    $this->getJson('/api/app/v1/organization', Accounts::headers($user))
        ->assertForbidden()
        ->assertJsonPath('code', 'organization_suspended');
});

it('blocks an unverified e-mail', function () {
    $user = Accounts::owner();
    $user->forceFill(['email_verified_at' => null, 'status' => UserStatus::PendingVerification])->save();

    $this->getJson('/api/app/v1/team/members', Accounts::headers($user))
        ->assertForbidden()
        ->assertJsonPath('code', 'email_not_verified');
});

it('blocks a user whose status is not active', function () {
    $user = Accounts::owner();
    $user->forceFill(['status' => UserStatus::Deleted])->save();

    $this->getJson('/api/app/v1/organization', Accounts::headers($user))
        ->assertForbidden()
        ->assertJsonPath('code', 'account_inactive');
});

it('passes guest routes through', function () {
    Accounts::seedCatalog();

    $this->getJson('/api/app/v1/lookups/regions')->assertOk();
});
