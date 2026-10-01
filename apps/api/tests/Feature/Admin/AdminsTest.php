<?php

declare(strict_types=1);

use App\Modules\Admin\Actions\DeleteAdmin;
use App\Modules\Admin\Actions\UpdateAdmin;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Auth\EditAdminProfile;
use App\Modules\Admin\Filament\Resources\Admins\Pages\CreateAdminAccount;
use App\Modules\Admin\Filament\Resources\Admins\Pages\EditAdminAccount;
use App\Modules\Admin\Filament\Resources\Admins\Pages\ListAdminAccounts;
use App\Modules\Admin\Models\Admin;
use App\Support\Audit\AuditLog;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Support\Admin\AdminPanel;

/*
 * §16 Admins: CRUD and "Reset MFA", super admins only. An admin never demotes, deactivates or
 * deletes their own account, and one active super admin always remains.
 */

beforeEach(function () {
    $this->admin = AdminPanel::signIn();
});

it('creates an admin with a hashed password', function () {
    Livewire::test(CreateAdminAccount::class)
        ->fillForm([
            'name' => 'Noura Ops',
            'email' => 'Noura.Ops@Bafo.Example',
            'role' => AdminRole::Operator->value,
            'is_active' => true,
            'password' => 'a-long-password-1',
            'password_confirmation' => 'a-long-password-1',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = Admin::query()->where('email', 'noura.ops@bafo.example')->sole();
    expect($created->role)->toBe(AdminRole::Operator)
        ->and(Hash::check('a-long-password-1', $created->password))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'admin.created')->value('subject_id'))->toBe($created->id);
});

it('validates a new admin', function () {
    Livewire::test(CreateAdminAccount::class)
        ->fillForm(['name' => '', 'email' => mb_strtoupper($this->admin->email), 'role' => 'operator', 'password' => 'short', 'password_confirmation' => 'other'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'email', 'password']);
});

it('edits an admin and keeps the password when left empty', function () {
    $operator = AdminPanel::operator();
    $hash = $operator->password;

    Livewire::test(EditAdminAccount::class, ['record' => $operator->public_id])
        ->fillForm(['role' => AdminRole::SuperAdmin->value, 'password' => '', 'password_confirmation' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    $operator->refresh();
    expect($operator->role)->toBe(AdminRole::SuperAdmin)
        ->and($operator->password)->toBe($hash)
        ->and(AuditLog::query()->where('action', 'admin.updated')->value('changes'))->toHaveKey('role');
});

it('resets the MFA of an admin that set it up', function () {
    $operator = AdminPanel::operator();
    $operator->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $operator->saveAppAuthenticationRecoveryCodes(['a', 'b']);

    Livewire::test(ListAdminAccounts::class)
        ->callAction(TestAction::make('resetMfa')->table($operator))
        ->assertNotified();

    $operator->refresh();
    expect($operator->app_authentication_secret)->toBeNull()
        ->and($operator->app_authentication_recovery_codes)->toBeNull()
        ->and(AuditLog::query()->where('action', 'admin.mfa_reset')->exists())->toBeTrue();
});

it('deletes another admin but never itself', function () {
    $operator = AdminPanel::operator();

    Livewire::test(ListAdminAccounts::class)
        ->assertActionHidden(TestAction::make('delete')->table($this->admin))
        ->callAction(TestAction::make('delete')->table($operator));

    expect(Admin::query()->whereKey($operator->id)->exists())->toBeFalse()
        ->and(fn () => app(DeleteAdmin::class)->handle($this->admin, Actor::forAdmin($this->admin)))
        ->toThrow(fn (ApiException $e) => expect($e->errorCode)->toBe('cannot_modify_self'));
});

it('refuses to demote or deactivate the last active super admin', function () {
    $other = AdminPanel::superAdmin();
    $actor = Actor::forAdmin($other);

    expect(fn () => app(UpdateAdmin::class)->handle($this->admin, ['role' => AdminRole::Operator], Actor::forAdmin($this->admin)))
        ->toThrow(fn (ApiException $e) => expect($e->errorCode)->toBe('cannot_modify_self'));

    app(UpdateAdmin::class)->handle($this->admin, ['is_active' => false], $actor);

    expect(fn () => app(UpdateAdmin::class)->handle($other, ['role' => AdminRole::Operator], Actor::forAdmin(AdminPanel::operator())))
        ->toThrow(fn (ApiException $e) => expect($e->errorCode)->toBe('conflict')->and($e->status)->toBe(409));

    expect($other->refresh()->role)->toBe(AdminRole::SuperAdmin);
});

it('is closed to operators', function () {
    AdminPanel::signIn(AdminPanel::operator());

    Livewire::test(ListAdminAccounts::class)->assertForbidden();
});

it('saves the own profile through UpdateAdmin', function () {
    Livewire::test(EditAdminProfile::class)
        ->fillForm(['name' => 'Renamed Admin'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->admin->refresh()->name)->toBe('Renamed Admin')
        ->and(AuditLog::query()->where('action', 'admin.updated')->where('subject_id', $this->admin->id)->exists())->toBeTrue();
});
