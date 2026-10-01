<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Resources\Users\Pages\ListUsers;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\Support\Admin\AdminPanel;

/*
 * §16 Users: search, view, deactivate / reactivate the membership (Identity ChangeMembershipStatus).
 */

beforeEach(function () {
    AdminPanel::signIn(AdminPanel::operator());
    $this->organization = Organization::factory()->create();
    Subscription::factory()->create(['organization_id' => $this->organization->id, 'seats' => 5]);
    $this->user = User::factory()->withMembership($this->organization, OrgRole::Member)->create(['name' => 'Layla Test', 'email' => 'layla@example.com']);
});

it('lists and searches users with their organization', function () {
    $other = User::factory()->withMembership(Organization::factory()->create(), OrgRole::Owner)->create();

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$this->user, $other])
        ->searchTable('layla@example.com')
        ->assertCanSeeTableRecords([$this->user])
        ->assertCanNotSeeTableRecords([$other]);

    $this->get('/admin/users/'.$this->user->public_id)->assertOk()->assertSee('Layla Test')->assertSee($this->organization->name);
});

it('filters by membership status', function () {
    $inactive = User::factory()->withMembership($this->organization, OrgRole::Member)->create();
    $inactive->membership->forceFill(['status' => MembershipStatus::Inactive])->save();

    Livewire::test(ListUsers::class)
        ->filterTable('membership_status', MembershipStatus::Inactive->value)
        ->assertCanSeeTableRecords([$inactive])
        ->assertCanNotSeeTableRecords([$this->user]);
});

it('deactivates and reactivates the membership from the list and the view page', function () {
    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('deactivateMembership')->table($this->user));

    expect($this->user->membership()->first()->status)->toBe(MembershipStatus::Inactive);

    Livewire::test(ViewUser::class, ['record' => $this->user->public_id])
        ->assertActionHidden('deactivateMembership')
        ->callAction('reactivateMembership');

    expect($this->user->membership()->first()->status)->toBe(MembershipStatus::Active);
});

it('reports the seat limit when a reactivation has no free seat', function () {
    $this->user->membership->forceFill(['status' => MembershipStatus::Inactive])->save();
    Subscription::query()->where('organization_id', $this->organization->id)->update(['seats' => 1]);
    User::factory()->withMembership($this->organization, OrgRole::Owner)->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('reactivateMembership')->table($this->user))
        ->assertNotified();

    expect($this->user->membership()->first()->status)->toBe(MembershipStatus::Inactive);
});
