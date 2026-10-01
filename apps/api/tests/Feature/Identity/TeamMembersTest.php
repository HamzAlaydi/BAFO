<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Events\MemberAdded;
use App\Modules\Identity\Events\MemberRemoved;
use App\Modules\Identity\Events\MemberUpdated;
use App\Modules\Identity\Mail\TeamInvitationMail;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\Identity\Accounts;

beforeEach(function () {
    Mail::fake();
});

function identityGiveSeats(Organization $organization, int $seats): void
{
    Subscription::factory()->create([
        'organization_id' => $organization->id,
        'plan_id' => Plan::factory()->create(['seats' => $seats])->id,
        'status' => SubscriptionStatus::Active,
        'seats' => $seats,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);
}

/**
 * @return array{0: User, 1: Organization}
 */
function identityTeamOwner(int $seats = 5): array
{
    $owner = Accounts::owner();
    $organization = $owner->membership->organization;
    identityGiveSeats($organization, $seats);

    return [$owner, $organization];
}

describe('GET /team/members', function () {
    it('lists the members with the owner first and the seats', function () {
        [$owner, $organization] = identityTeamOwner(3);
        $admin = Accounts::member($organization, OrgRole::Admin);
        Membership::factory()->invited()->for($organization)->create();
        Accounts::member(Organization::factory()->create());

        $response = $this->getJson('/api/app/v1/team/members', Accounts::headers($owner))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'role', 'can_award', 'can_purchase', 'status', 'joined_at', 'invited_at', 'user' => ['id', 'name', 'email']]], 'meta' => ['seats', 'server_time']])
            ->assertJsonPath('meta.seats', ['used' => 3, 'total' => 3])
            ->assertJsonPath('data.0.role', 'owner')
            ->assertJsonPath('data.0.user.id', $owner->public_id)
            ->assertJsonPath('data.1.user.id', $admin->public_id);

        expect($response->json('data'))->toHaveCount(3);
    });

    it('filters by status', function () {
        [$owner, $organization] = identityTeamOwner();
        Membership::factory()->invited()->for($organization)->create();

        $this->getJson('/api/app/v1/team/members?status=invited', Accounts::headers($owner))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'invited');

        $this->getJson('/api/app/v1/team/members?status=gone', Accounts::headers($owner))->assertUnprocessable();
    });

    it('needs team.manage', function () {
        $member = Accounts::member(Organization::factory()->create());

        $this->getJson('/api/app/v1/team/members', Accounts::headers($member))->assertForbidden()->assertJsonPath('code', 'forbidden');
    });

    it('requires a token', function () {
        $this->getJson('/api/app/v1/team/members')->assertUnauthorized();
    });
});

describe('POST /team/members', function () {
    it('invites a member by e-mail with a one-time link in the fragment', function () {
        Event::fake([MemberAdded::class]);
        [$owner, $organization] = identityTeamOwner();

        $response = $this->postJson('/api/app/v1/team/members', [
            'name' => 'منى القحطاني',
            'email' => 'Mona@Acme.SA',
            'phone' => '+966512345678',
            'role' => 'member',
        ], Accounts::headers($owner))
            ->assertCreated()
            ->assertJsonPath('data.status', 'invited')
            ->assertJsonPath('data.role', 'member')
            ->assertJsonPath('data.can_award', false)
            ->assertJsonPath('data.can_purchase', false)
            ->assertJsonPath('data.user.email', 'mona@acme.sa')
            ->assertJsonPath('data.user.status', 'pending_verification')
            ->assertJsonPath('data.joined_at', null);

        expect($response->json('data.invited_at'))->toBeIso8601Utc();

        $membership = Membership::query()->wherePublicId($response->json('data.id'))->sole();
        expect($membership->invite_expires_at?->isSameDay(now()->addDays(7)))->toBeTrue()
            ->and($membership->invited_by_user_id)->toBe($owner->id)
            ->and($membership->user->password)->toBeNull();

        Mail::assertQueued(TeamInvitationMail::class, function (TeamInvitationMail $mail) use ($membership): bool {
            preg_match('~/ar/auth/accept-invite#t=([A-Za-z0-9_-]+)$~', $mail->acceptUrl, $matches);

            return $mail->hasTo('mona@acme.sa')
                && str_starts_with($mail->acceptUrl, 'http://localhost:3000/ar/auth/accept-invite#t=')
                && ! str_contains($mail->acceptUrl, '?')
                && isset($matches[1])
                && hash('sha256', $matches[1]) === $membership->invite_token_hash;
        });

        Event::assertDispatched(MemberAdded::class);
        expect(AuditLog::query()->where('action', 'member.added')->sole()->organization_id)->toBe($organization->id);
    });

    it('gives an admin award and purchase rights by default', function () {
        [$owner] = identityTeamOwner();

        $this->postJson('/api/app/v1/team/members', ['name' => 'Khalid', 'email' => 'khalid@acme.sa', 'role' => 'admin'], Accounts::headers($owner))
            ->assertCreated()
            ->assertJsonPath('data.can_award', true)
            ->assertJsonPath('data.can_purchase', true);

        $this->postJson('/api/app/v1/team/members', ['name' => 'Noura', 'email' => 'noura@acme.sa', 'role' => 'member', 'can_award' => true], Accounts::headers($owner))
            ->assertCreated()
            ->assertJsonPath('data.can_award', true)
            ->assertJsonPath('data.can_purchase', false);
    });

    it('stops at the seat limit', function () {
        [$owner, $organization] = identityTeamOwner(2);
        Membership::factory()->invited()->for($organization)->create();

        $this->postJson('/api/app/v1/team/members', ['name' => 'Late', 'email' => 'late@acme.sa', 'role' => 'member'], Accounts::headers($owner))
            ->assertStatus(409)
            ->assertJsonPath('code', 'seat_limit_reached')
            ->assertJsonPath('details.seats', ['used' => 2, 'total' => 2]);

        expect(User::query()->where('email', 'late@acme.sa')->exists())->toBeFalse();
        Mail::assertNothingQueued();
    });

    it('allows one seat without a plan', function () {
        $owner = Accounts::owner();

        $this->postJson('/api/app/v1/team/members', ['name' => 'Late', 'email' => 'late@acme.sa', 'role' => 'member'], Accounts::headers($owner))
            ->assertStatus(409)
            ->assertJsonPath('details.seats', ['used' => 1, 'total' => 1]);
    });

    it('refuses an e-mail that already has an account', function () {
        [$owner] = identityTeamOwner();
        User::factory()->create(['email' => 'taken@acme.sa']);

        $this->postJson('/api/app/v1/team/members', ['name' => 'X', 'email' => 'taken@acme.sa', 'role' => 'member'], Accounts::headers($owner))
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', __('identity.validation.email_taken'));
    });

    it('validates the fields', function () {
        [$owner] = identityTeamOwner();

        $this->postJson('/api/app/v1/team/members', ['email' => 'nope', 'role' => 'owner', 'phone' => '123'], Accounts::headers($owner))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'role', 'phone']);
    });

    it('needs team.manage before validating', function () {
        $member = Accounts::member(Organization::factory()->create());

        $this->postJson('/api/app/v1/team/members', [], Accounts::headers($member))->assertForbidden();
    });
});

describe('PATCH /team/members/{membership}', function () {
    it('changes the role and the flags', function () {
        Event::fake([MemberUpdated::class]);
        [$owner, $organization] = identityTeamOwner();
        $member = Accounts::member($organization);

        $this->patchJson('/api/app/v1/team/members/'.$member->membership->public_id, ['role' => 'admin', 'can_award' => true], Accounts::headers($owner))
            ->assertOk()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.can_award', true)
            ->assertJsonPath('data.can_purchase', false);

        Event::assertDispatched(MemberUpdated::class, fn (MemberUpdated $event): bool => array_keys($event->changes) === ['role', 'can_award']);
        expect(AuditLog::query()->where('action', 'member.updated')->sole()->changes)->toHaveKeys(['role', 'can_award']);
    });

    it('deactivates and reactivates a member within the seats', function () {
        [$owner, $organization] = identityTeamOwner(2);
        $member = Accounts::member($organization);
        $path = '/api/app/v1/team/members/'.$member->membership->public_id;

        $this->patchJson($path, ['status' => 'inactive'], Accounts::headers($owner))->assertOk()->assertJsonPath('data.status', 'inactive');

        Membership::factory()->invited()->for($organization)->create();

        $this->patchJson($path, ['status' => 'active'], Accounts::headers($owner))
            ->assertStatus(409)
            ->assertJsonPath('code', 'seat_limit_reached');
    });

    it('cannot change the owner or yourself', function () {
        [$owner, $organization] = identityTeamOwner();
        $admin = Accounts::member($organization, OrgRole::Admin);

        $this->patchJson('/api/app/v1/team/members/'.$owner->membership->public_id, ['role' => 'member'], Accounts::headers($admin))
            ->assertStatus(409)
            ->assertJsonPath('code', 'cannot_modify_owner');

        $this->patchJson('/api/app/v1/team/members/'.$admin->membership->public_id, ['can_purchase' => false], Accounts::headers($admin))
            ->assertStatus(409)
            ->assertJsonPath('code', 'cannot_modify_self');
    });

    it('cannot activate a pending invitation', function () {
        [$owner, $organization] = identityTeamOwner();
        $invited = Membership::factory()->invited()->for($organization)->create();

        $this->patchJson('/api/app/v1/team/members/'.$invited->public_id, ['status' => 'active'], Accounts::headers($owner))
            ->assertStatus(409)
            ->assertJsonPath('code', 'invalid_state_transition')
            ->assertJsonPath('details', ['from' => 'invited', 'to' => 'active']);
    });

    it('hides the members of other organizations', function () {
        [$owner] = identityTeamOwner();
        $stranger = Accounts::member(Organization::factory()->create());

        $this->patchJson('/api/app/v1/team/members/'.$stranger->membership->public_id, ['role' => 'admin'], Accounts::headers($owner))
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        $this->patchJson('/api/app/v1/team/members/not-a-ulid', ['role' => 'admin'], Accounts::headers($owner))->assertNotFound();
    });

    it('needs team.manage', function () {
        $organization = Organization::factory()->create();
        $member = Accounts::member($organization);
        $other = Accounts::member($organization);

        $this->patchJson('/api/app/v1/team/members/'.$other->membership->public_id, ['role' => 'admin'], Accounts::headers($member))->assertForbidden();
    });

    it('validates the fields', function () {
        [$owner, $organization] = identityTeamOwner();
        $member = Accounts::member($organization);

        $this->patchJson('/api/app/v1/team/members/'.$member->membership->public_id, ['role' => 'owner', 'status' => 'invited', 'can_award' => 'maybe'], Accounts::headers($owner))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role', 'status', 'can_award']);
    });
});

describe('DELETE /team/members/{membership}', function () {
    it('removes the member, anonymises the user and revokes their tokens', function () {
        Event::fake([MemberRemoved::class]);
        [$owner, $organization] = identityTeamOwner();
        $member = Accounts::member($organization);
        $memberToken = $member->createToken('web')->plainTextToken;

        $this->deleteJson('/api/app/v1/team/members/'.$member->membership->public_id, [], Accounts::headers($owner))->assertNoContent();

        $user = User::withTrashed()->findOrFail($member->id);

        expect(Membership::query()->where('user_id', $member->id)->exists())->toBeFalse()
            ->and($user->trashed())->toBeTrue()
            ->and($user->status)->toBe(UserStatus::Deleted)
            ->and($user->email)->toBe('deleted+'.$user->public_id.'@invalid.bafo')
            ->and($user->phone)->toBeNull()
            ->and(PersonalAccessToken::query()->where('tokenable_id', $member->id)->exists())->toBeFalse()
            ->and(AuditLog::query()->where('action', 'member.removed')->exists())->toBeTrue();

        Event::assertDispatched(MemberRemoved::class);
        $this->getJson('/api/app/v1/me', Accounts::bearer($memberToken))->assertUnauthorized();
    });

    it('cannot remove the owner or yourself', function () {
        [$owner, $organization] = identityTeamOwner();
        $admin = Accounts::member($organization, OrgRole::Admin);

        $this->deleteJson('/api/app/v1/team/members/'.$owner->membership->public_id, [], Accounts::headers($admin))
            ->assertStatus(409)->assertJsonPath('code', 'cannot_modify_owner');

        $this->deleteJson('/api/app/v1/team/members/'.$admin->membership->public_id, [], Accounts::headers($admin))
            ->assertStatus(409)->assertJsonPath('code', 'cannot_modify_self');
    });

    it('hides the members of other organizations', function () {
        [$owner] = identityTeamOwner();
        $stranger = Accounts::member(Organization::factory()->create());

        $this->deleteJson('/api/app/v1/team/members/'.$stranger->membership->public_id, [], Accounts::headers($owner))->assertNotFound();
        expect(Membership::query()->whereKey($stranger->membership->id)->exists())->toBeTrue();
    });
});

describe('POST /team/members/{membership}/resend-invitation', function () {
    it('issues a new token so the old link stops working', function () {
        [$owner, $organization] = identityTeamOwner();
        $invited = Membership::factory()->invited('old-token')->for($organization)->create();

        $this->postJson('/api/app/v1/team/members/'.$invited->public_id.'/resend-invitation', [], Accounts::headers($owner))->assertNoContent();

        expect($invited->refresh()->invite_token_hash)->not->toBe(hash('sha256', 'old-token'));
        Mail::assertQueued(TeamInvitationMail::class, fn (TeamInvitationMail $mail): bool => hash('sha256', explode('#t=', $mail->acceptUrl)[1]) === $invited->invite_token_hash);

        $this->postJson('/api/app/v1/auth/team-invitations/lookup', ['token' => 'old-token'])->assertUnprocessable()->assertJsonPath('code', 'team_invitation_invalid');
    });

    it('only resends pending invitations', function () {
        [$owner, $organization] = identityTeamOwner();
        $member = Accounts::member($organization);

        $this->postJson('/api/app/v1/team/members/'.$member->membership->public_id.'/resend-invitation', [], Accounts::headers($owner))
            ->assertStatus(409)
            ->assertJsonPath('code', 'invalid_state_transition')
            ->assertJsonPath('details.status', MembershipStatus::Active->value);
    });

    it('needs team.manage', function () {
        $organization = Organization::factory()->create();
        $member = Accounts::member($organization);
        $invited = Membership::factory()->invited()->for($organization)->create();

        $this->postJson('/api/app/v1/team/members/'.$invited->public_id.'/resend-invitation', [], Accounts::headers($member))->assertForbidden();
    });
});

it('never turns user-entered names in the team invitation mail into links', function () {
    $html = (new TeamInvitationMail(
        inviteeName: '[Ali](https://evil.example/a)',
        organizationName: '**Acme** <b>Co</b>',
        inviterName: '[Sara](https://evil.example/s)',
        role: OrgRole::Member,
        acceptUrl: 'https://app.bafo.test/ar/auth/accept-invite#t=abc',
        expiresAt: CarbonImmutable::parse('2026-10-06 12:00:00'),
    ))->locale('en')->render();

    expect($html)->not->toContain('href="https://evil.example')
        ->and($html)->not->toContain('<strong>Acme</strong>')
        ->and($html)->toContain('&lt;b&gt;Co&lt;/b&gt;', 'href="https://app.bafo.test/ar/auth/accept-invite#t=abc"');
});
