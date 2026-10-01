<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Identity\Accounts;

/*
 * Security review (docs/build/SECURITY_REVIEW.md) S-02: `team.manage` must not be a way around
 * `can_award` and `can_purchase`. An admin whose own membership lacks a flag cannot grant it to a
 * colleague, nor to a new team member (for instance a second account of its own), neither
 * explicitly nor through the admin defaults. Revoking a flag and keeping an unchanged one stay
 * allowed, so the apps can keep sending the full form.
 */

beforeEach(function () {
    Mail::fake();
});

/**
 * An organization with seats, its owner and an admin without `can_award` and `can_purchase`.
 *
 * @return array{0: Organization, 1: User, 2: User}
 */
function securityRestrictedAdmin(): array
{
    $owner = Accounts::owner();
    $organization = $owner->membership->organization;

    Subscription::factory()->create([
        'organization_id' => $organization->id,
        'plan_id' => Plan::factory()->create(['seats' => 10])->id,
        'status' => SubscriptionStatus::Active,
        'seats' => 10,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);

    $admin = Accounts::member($organization, OrgRole::Admin);
    Membership::query()->where('user_id', $admin->id)->update(['can_award' => false, 'can_purchase' => false]);

    return [$organization, $owner, $admin->refresh()];
}

describe('adding a team member', function () {
    it('refuses an explicit grant of a flag the inviter does not hold', function (string $flag) {
        [, , $admin] = securityRestrictedAdmin();

        $this->postJson('/api/app/v1/team/members', [
            'name' => 'Sock Puppet', 'email' => 'puppet@acme.sa', 'role' => 'member', $flag => true,
        ], Accounts::headers($admin))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$flag]);

        expect(User::query()->where('email', 'puppet@acme.sa')->exists())->toBeFalse();
    })->with(['can_award', 'can_purchase']);

    it('does not hand the admin defaults to a new admin when the inviter lacks them', function () {
        [, , $admin] = securityRestrictedAdmin();

        $this->postJson('/api/app/v1/team/members', [
            'name' => 'Sock Puppet', 'email' => 'puppet@acme.sa', 'role' => 'admin',
        ], Accounts::headers($admin))
            ->assertCreated()
            ->assertJsonPath('data.can_award', false)
            ->assertJsonPath('data.can_purchase', false);
    });

    it('still gives a new admin both flags by default when the owner invites', function () {
        [, $owner] = securityRestrictedAdmin();

        $this->postJson('/api/app/v1/team/members', [
            'name' => 'Khalid', 'email' => 'khalid@acme.sa', 'role' => 'admin',
        ], Accounts::headers($owner))
            ->assertCreated()
            ->assertJsonPath('data.can_award', true)
            ->assertJsonPath('data.can_purchase', true);
    });
});

describe('updating a team member', function () {
    it('refuses to grant a colleague a flag the editor does not hold', function (string $flag) {
        [$organization, , $admin] = securityRestrictedAdmin();
        $member = Accounts::member($organization);

        $this->patchJson('/api/app/v1/team/members/'.$member->membership->public_id, [$flag => true], Accounts::headers($admin))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$flag]);

        expect((bool) $member->membership->refresh()->getAttribute($flag))->toBeFalse();
    })->with(['can_award', 'can_purchase']);

    it('lets the editor revoke a flag and resend an unchanged one', function () {
        [$organization, , $admin] = securityRestrictedAdmin();
        $colleague = Accounts::member($organization, OrgRole::Admin);
        Membership::query()->where('user_id', $colleague->id)->update(['can_award' => true, 'can_purchase' => true]);
        $id = $colleague->membership->public_id;

        // The web form sends every field: an unchanged `true` is not a grant.
        $this->patchJson('/api/app/v1/team/members/'.$id, ['role' => 'admin', 'can_award' => true, 'can_purchase' => false], Accounts::headers($admin))
            ->assertOk()
            ->assertJsonPath('data.can_award', true)
            ->assertJsonPath('data.can_purchase', false);
    });

    it('lets an editor holding the flag grant it', function () {
        [$organization, $owner] = securityRestrictedAdmin();
        $member = Accounts::member($organization);

        $this->patchJson('/api/app/v1/team/members/'.$member->membership->public_id, ['can_award' => true, 'can_purchase' => true], Accounts::headers($owner))
            ->assertOk()
            ->assertJsonPath('data.can_award', true)
            ->assertJsonPath('data.can_purchase', true);
    });
});
