<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Actions\ChangeMembershipStatus;
use App\Modules\Identity\Actions\ResendOwnerVerificationCode;
use App\Modules\Identity\Actions\SuspendOrganization;
use App\Modules\Identity\Actions\UnsuspendOrganization;
use App\Modules\Identity\Actions\UpdateOrganizationFeatures;
use App\Modules\Identity\Actions\VerifyOrganization;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Events\MemberUpdated;
use App\Modules\Identity\Events\OrganizationUpdated;
use App\Modules\Identity\Mail\OtpCodeMail;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Identity\Accounts;

it('verifies and unverifies an organization', function () {
    Event::fake([OrganizationUpdated::class]);
    $organization = Organization::factory()->create();

    $verified = app(VerifyOrganization::class)->handle($organization, true, Actor::system());
    expect($verified->isVerified())->toBeTrue();

    app(VerifyOrganization::class)->handle($organization, false, Actor::system());
    expect($organization->refresh()->isVerified())->toBeFalse()
        ->and(AuditLog::query()->orderBy('id')->pluck('action')->all())->toBe(['organization.verified', 'organization.unverified']);

    Event::assertDispatchedTimes(OrganizationUpdated::class, 2);
});

it('suspends and reactivates an organization, which the account gate enforces', function () {
    $owner = Accounts::owner();
    $organization = $owner->membership->organization;
    $headers = Accounts::headers($owner);

    app(SuspendOrganization::class)->handle($organization, 'مخالفة شروط الاستخدام', Actor::system());

    expect($organization->refresh())
        ->status->toBe(OrganizationStatus::Suspended)
        ->suspension_reason->toBe('مخالفة شروط الاستخدام')
        ->suspended_at->not->toBeNull();
    $this->getJson('/api/app/v1/organization', $headers)->assertForbidden()->assertJsonPath('code', 'organization_suspended');

    app(UnsuspendOrganization::class)->handle($organization, Actor::system());

    expect($organization->refresh()->status)->toBe(OrganizationStatus::Active)
        ->and($organization->suspension_reason)->toBeNull();
    $this->getJson('/api/app/v1/organization', Accounts::headers($owner))->assertOk();
});

it('refuses to suspend an organization twice', function () {
    $organization = Organization::factory()->suspended()->create();

    expect(fn () => app(SuspendOrganization::class)->handle($organization, 'x', Actor::system()))
        ->toThrow(ApiException::class, 'invalid_state_transition');
});

it('toggles the organization features', function () {
    Event::fake([OrganizationUpdated::class]);
    $organization = Organization::factory()->create();

    app(UpdateOrganizationFeatures::class)->handle($organization, ['api_enabled' => true, 'auction_enabled' => true], Actor::system());

    expect($organization->refresh())
        ->api_enabled->toBeTrue()
        ->auction_enabled->toBeTrue()
        ->sponsorship_enabled->toBeFalse();

    Event::assertDispatched(OrganizationUpdated::class, fn (OrganizationUpdated $event): bool => $event->changedFields === ['api_enabled', 'auction_enabled']);
});

it("resends the owner's verification code", function () {
    Mail::fake();
    $organization = Organization::factory()->create();
    User::factory()->unverified()->withMembership($organization, OrgRole::Owner)->create(['email' => 'owner@acme.sa']);

    expect(app(ResendOwnerVerificationCode::class)->handle($organization, Actor::system()))->not->toBeNull();
    Mail::assertQueued(OtpCodeMail::class, fn (OtpCodeMail $mail): bool => $mail->hasTo('owner@acme.sa'));

    $verified = Accounts::owner();
    expect(app(ResendOwnerVerificationCode::class)->handle($verified->membership->organization, Actor::system()))->toBeNull();
});

it('deactivates and reactivates a membership within the seats', function () {
    Event::fake([MemberUpdated::class]);
    $owner = Accounts::owner();
    $organization = $owner->membership->organization;
    Subscription::factory()->create([
        'organization_id' => $organization->id,
        'plan_id' => Plan::factory()->create()->id,
        'status' => SubscriptionStatus::Active,
        'seats' => 2,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);
    $member = Accounts::member($organization);

    $membership = app(ChangeMembershipStatus::class)->handle($member->membership, MembershipStatus::Inactive, Actor::system());
    expect($membership->status)->toBe(MembershipStatus::Inactive);

    Accounts::member($organization);

    expect(fn () => app(ChangeMembershipStatus::class)->handle($member->membership, MembershipStatus::Active, Actor::system()))
        ->toThrow(ApiException::class, 'seat_limit_reached');

    Event::assertDispatchedTimes(MemberUpdated::class, 1);
});
