<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Events\MemberUpdated;
use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\Support\Identity\Accounts;

function identityInvitation(string $token = 'plain-token', ?Organization $organization = null): Membership
{
    $organization ??= Organization::factory()->create(['name' => 'شركة المصدر']);
    $user = User::factory()->invited()->create([
        'name' => 'منى',
        'email' => 'mona@acme.sa',
        'status' => UserStatus::PendingVerification,
    ]);

    return Membership::factory()->invited($token)->for($organization)->for($user)->role(OrgRole::Member)->create();
}

function identityAccept(string $token, array $overrides = []): TestResponse
{
    return test()->postJson('/api/app/v1/auth/team-invitations/accept', [
        'token' => $token,
        'password' => Accounts::PASSWORD,
        'password_confirmation' => Accounts::PASSWORD,
        'device_name' => 'Chrome · web',
        'accept_terms' => true,
        ...$overrides,
    ]);
}

describe('lookup', function () {
    it('describes a pending invitation', function () {
        $membership = identityInvitation();

        $this->postJson('/api/app/v1/auth/team-invitations/lookup', ['token' => 'plain-token'])
            ->assertOk()
            ->assertJsonPath('data', [
                'email' => 'mona@acme.sa',
                'name' => 'منى',
                'organization' => ['name' => 'شركة المصدر', 'logo_url' => null],
                'role' => 'member',
                'expires_at' => $membership->invite_expires_at?->utc()->format('Y-m-d\TH:i:s.v\Z'),
            ]);
    });

    it('rejects an unknown or expired token', function () {
        identityInvitation();

        $this->postJson('/api/app/v1/auth/team-invitations/lookup', ['token' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'team_invitation_invalid');

        $this->travel(8)->days();

        $this->postJson('/api/app/v1/auth/team-invitations/lookup', ['token' => 'plain-token'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'team_invitation_invalid');
    });

    it('validates the token', function () {
        $this->postJson('/api/app/v1/auth/team-invitations/lookup', [])->assertUnprocessable()->assertJsonValidationErrors(['token']);
    });
});

describe('accept', function () {
    it('activates the member and signs them in', function () {
        Event::fake([MemberUpdated::class]);
        Accounts::publishLegalDocuments();
        $membership = identityInvitation();

        $response = identityAccept('plain-token')
            ->assertOk()
            ->assertJsonPath('data.user.status', 'active')
            ->assertJsonPath('data.membership.status', 'active')
            ->assertJsonPath('data.token_type', 'Bearer');

        $membership->refresh();
        $user = $membership->user;

        expect($membership->status)->toBe(MembershipStatus::Active)
            ->and($membership->joined_at)->not->toBeNull()
            ->and($membership->invite_token_hash)->toBeNull()
            ->and($user->email_verified_at)->not->toBeNull()
            ->and($user->status)->toBe(UserStatus::Active)
            ->and(Hash::check(Accounts::PASSWORD, (string) $user->password))->toBeTrue()
            ->and(Consent::query()->where('user_id', $user->id)->pluck('document_code')->map->value->all())->toBe(['terms']);

        Event::assertDispatched(MemberUpdated::class);

        $this->getJson('/api/app/v1/team/members', Accounts::bearer($response->json('data.token')))->assertForbidden();
        $this->getJson('/api/app/v1/me', Accounts::bearer($response->json('data.token')))->assertOk();

        identityAccept('plain-token')->assertUnprocessable()->assertJsonPath('code', 'team_invitation_invalid');
    });

    it('rejects an expired invitation', function () {
        identityInvitation();
        $this->travel(8)->days();

        identityAccept('plain-token')->assertUnprocessable()->assertJsonPath('code', 'team_invitation_invalid');
    });

    it('refuses to sign in to a suspended organization', function () {
        identityInvitation(organization: Organization::factory()->suspended()->create());

        identityAccept('plain-token')->assertForbidden()->assertJsonPath('code', 'organization_suspended');
        expect(Membership::query()->sole()->status)->toBe(MembershipStatus::Invited);
    });

    it('validates the input', function () {
        identityInvitation();

        identityAccept('plain-token', ['accept_terms' => false, 'password' => 'weak', 'password_confirmation' => 'weak', 'device_name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accept_terms', 'password', 'device_name']);
    });
});
