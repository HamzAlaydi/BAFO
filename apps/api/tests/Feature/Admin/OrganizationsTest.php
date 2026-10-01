<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Modules\Admin\Filament\Resources\Organizations\Pages\ViewOrganization;
use App\Modules\Admin\Filament\Resources\Organizations\RelationManagers\CompetitionsRelationManager;
use App\Modules\Admin\Filament\Resources\Organizations\RelationManagers\MembersRelationManager;
use App\Modules\Admin\Filament\Resources\Organizations\RelationManagers\SubscriptionsRelationManager;
use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLog;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\Admin\AdminPanel;

/*
 * §16 Organizations: search and view; verify, suspend, feature flags, resend the owner's OTP,
 * grant a subscription — each through the Identity or Billing Action, audited as the admin.
 */

function adminOrgAudit(string $action): ?AuditLog
{
    return AuditLog::query()->where('action', $action)->latest('id')->first();
}

beforeEach(function () {
    $this->admin = AdminPanel::signIn(AdminPanel::operator());
    $this->organization = Organization::factory()->create(['name' => 'Acme Trading', 'cr_number' => '1010999999']);
    $this->owner = User::factory()->withMembership($this->organization, OrgRole::Owner)->create();
});

it('lists and searches organizations', function () {
    $other = Organization::factory()->create(['name' => 'Other Supplies']);

    Livewire::test(ListOrganizations::class)
        ->assertCanSeeTableRecords([$this->organization, $other])
        ->searchTable('1010999999')
        ->assertCanSeeTableRecords([$this->organization])
        ->assertCanNotSeeTableRecords([$other])
        ->filterTable('status', OrganizationStatus::Suspended->value)
        ->assertCanNotSeeTableRecords([$this->organization]);
});

it('shows the organization with its members, subscriptions and competitions', function () {
    $competition = Competition::factory()->create(['organization_id' => $this->organization->id]);
    $subscription = Subscription::factory()->create(['organization_id' => $this->organization->id]);

    $this->get('/admin/organizations/'.$this->organization->public_id)
        ->assertOk()
        ->assertSee('Acme Trading')
        ->assertSee('1010999999');

    $context = ['ownerRecord' => $this->organization, 'pageClass' => ViewOrganization::class];

    Livewire::test(MembersRelationManager::class, $context)->assertCanSeeTableRecords([$this->owner->membership]);
    Livewire::test(SubscriptionsRelationManager::class, $context)->assertCanSeeTableRecords([$subscription]);
    Livewire::test(CompetitionsRelationManager::class, $context)->assertCanSeeTableRecords([$competition]);
});

it('verifies and unverifies through VerifyOrganization', function () {
    Livewire::test(ViewOrganization::class, ['record' => $this->organization->public_id])
        ->callAction('verify')
        ->assertNotified();

    expect($this->organization->refresh()->verified_at)->not->toBeNull()
        ->and(adminOrgAudit('organization.verified')?->actor_id)->toBe($this->admin->id);

    Livewire::test(ViewOrganization::class, ['record' => $this->organization->public_id])
        ->assertActionHidden('verify')
        ->callAction('unverify');

    expect($this->organization->refresh()->verified_at)->toBeNull()
        ->and(adminOrgAudit('organization.unverified'))->not->toBeNull();
});

it('suspends with a reason and lifts the suspension', function () {
    Livewire::test(ViewOrganization::class, ['record' => $this->organization->public_id])
        ->callAction('suspend', data: ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($this->organization->refresh()->status)->toBe(OrganizationStatus::Active);

    Livewire::test(ViewOrganization::class, ['record' => $this->organization->public_id])
        ->callAction('suspend', data: ['reason' => 'Fraud report under review'])
        ->assertHasNoActionErrors();

    $this->organization->refresh();
    expect($this->organization->status)->toBe(OrganizationStatus::Suspended)
        ->and($this->organization->suspension_reason)->toBe('Fraud report under review');

    Livewire::test(ViewOrganization::class, ['record' => $this->organization->public_id])
        ->assertActionHidden('suspend')
        ->callAction('unsuspend');

    expect($this->organization->refresh()->status)->toBe(OrganizationStatus::Active);
});

it('toggles the feature flags through UpdateOrganizationFeatures', function () {
    Livewire::test(ViewOrganization::class, ['record' => $this->organization->public_id])
        ->callAction('features', data: ['api_enabled' => true, 'auction_enabled' => true, 'sponsorship_enabled' => false])
        ->assertHasNoActionErrors();

    $this->organization->refresh();
    expect($this->organization->api_enabled)->toBeTrue()
        ->and($this->organization->auction_enabled)->toBeTrue()
        ->and($this->organization->sponsorship_enabled)->toBeFalse()
        ->and(adminOrgAudit('organization.features_updated')?->changes)->toHaveKeys(['api_enabled', 'auction_enabled']);
});

it('resends the verification code to an unverified owner', function () {
    Mail::fake();
    $this->owner->forceFill(['email_verified_at' => null])->save();

    Livewire::test(ViewOrganization::class, ['record' => $this->organization->public_id])
        ->callAction('resendVerification')
        ->assertNotified();

    expect(OtpCode::query()->where('email', $this->owner->email)->where('purpose', OtpPurpose::EmailVerification->value)->exists())->toBeTrue();
});

it('hides the resend action when the owner is verified', function () {
    Livewire::test(ViewOrganization::class, ['record' => $this->organization->public_id])
        ->assertActionHidden('resendVerification');
});

it('grants a subscription through GrantSubscription', function () {
    $plan = Plan::factory()->create(['seats' => 3]);

    Livewire::test(ViewOrganization::class, ['record' => $this->organization->public_id])
        ->callAction('grantSubscription', data: [
            'plan_id' => $plan->id,
            'seats' => 5,
            'starts_at' => now('Asia/Riyadh')->subMinute()->toDateTimeString(),
            'ends_at' => now('Asia/Riyadh')->addMonths(6)->toDateTimeString(),
            'reason' => 'Launch partner',
        ])
        ->assertHasNoActionErrors();

    $subscription = Subscription::query()->where('organization_id', $this->organization->id)->sole();
    expect($subscription->source)->toBe(SubscriptionSource::Grant)
        ->and($subscription->starts_at->lessThanOrEqualTo(now()))->toBeTrue()
        ->and($subscription->starts_at->greaterThan(now()->subMinutes(5)))->toBeTrue()
        ->and($subscription->seats)->toBe(5)
        ->and($subscription->granted_by_admin_id)->toBe($this->admin->id)
        ->and($subscription->grant_reason)->toBe('Launch partner');
});

it('deactivates and reactivates a member through ChangeMembershipStatus', function () {
    $member = User::factory()->withMembership($this->organization, OrgRole::Member)->create();
    Subscription::factory()->create(['organization_id' => $this->organization->id, 'seats' => 5]);
    $membership = Membership::query()->where('user_id', $member->id)->sole();
    $context = ['ownerRecord' => $this->organization, 'pageClass' => ViewOrganization::class];

    Livewire::test(MembersRelationManager::class, $context)
        ->callAction(TestAction::make('deactivateMembership')->table($membership));

    expect($membership->refresh()->status)->toBe(MembershipStatus::Inactive);

    Livewire::test(MembersRelationManager::class, $context)
        ->assertActionHidden(TestAction::make('deactivateMembership')->table($membership))
        ->callAction(TestAction::make('reactivateMembership')->table($membership));

    expect($membership->refresh()->status)->toBe(MembershipStatus::Active)
        ->and(adminOrgAudit('member.updated')?->actor_type->value)->toBe('admin');
});
