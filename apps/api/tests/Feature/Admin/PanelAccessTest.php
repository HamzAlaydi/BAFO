<?php

declare(strict_types=1);

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Support\AdminLocale;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLog;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Support\Admin\AdminPanel;

/*
 * The platform admin panel (ARCHITECTURE §8.7, §16): guard `admin`, super admin / operator roles,
 * MFA, Arabic (RTL) with an English toggle.
 */

it('renders every §16 page for a super admin', function (string $url) {
    $this->actingAs(AdminPanel::superAdmin(), 'admin')->get($url)->assertOk();
})->with([...AdminPanel::INDEXES, ...AdminPanel::SUPER_ADMIN_ONLY, '/admin/profile']);

it('sends guests to the admin login page', function (string $url) {
    $this->get($url)->assertRedirect('/admin/login');
})->with(AdminPanel::INDEXES);

it('does not let an organization user into the panel', function (string $url) {
    $user = User::factory()->withMembership(Organization::factory()->create(), OrgRole::Owner)->create();

    $this->actingAs($user, 'web')->get($url)->assertRedirect('/admin/login');
})->with(['/admin', '/admin/organizations', '/admin/payments']);

it('forbids an inactive admin', function (string $url) {
    $this->actingAs(AdminPanel::superAdmin(['is_active' => false]), 'admin')->get($url)->assertForbidden();
})->with(['/admin', '/admin/organizations', '/admin/competitions']);

it('lets an operator use the operations pages', function (string $url) {
    $this->actingAs(AdminPanel::operator(), 'admin')->get($url)->assertOk();
})->with(AdminPanel::INDEXES);

it('keeps admins, plans, coupons and settings from operators', function (string $url) {
    $this->actingAs(AdminPanel::operator(), 'admin')->get($url)->assertForbidden();
})->with(AdminPanel::SUPER_ADMIN_ONLY);

it('signs an admin in on the admin guard, stamps last_login_at and audits it', function () {
    $admin = AdminPanel::superAdmin(['email' => 'ops@bafo.example']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(Login::class)
        ->fillForm(['email' => 'ops@bafo.example', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth('admin')->id())->toBe($admin->id)
        ->and($admin->refresh()->last_login_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'admin.signed_in')->where('actor_id', $admin->id)->value('actor_type')?->value)->toBe('admin');
});

it('does not accept organization user credentials on the admin login', function () {
    User::factory()->withMembership(Organization::factory()->create(), OrgRole::Owner)->create(['email' => 'owner@example.com']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(Login::class)
        ->fillForm(['email' => 'owner@example.com', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(auth('admin')->check())->toBeFalse();
});

it('requires app authentication when bafo.admin.mfa_required is on', function (bool $required) {
    config(['bafo.admin.mfa_required' => $required]);
    $panel = Filament::getPanel('admin');

    expect($panel->hasMultiFactorAuthentication())->toBeTrue()
        ->and(array_keys($panel->getMultiFactorAuthenticationProviders()))->toBe(['app'])
        ->and($panel->isMultiFactorAuthenticationRequired())->toBe($required);
})->with([true, false]);

it('uses the admin guard and the admins provider', function () {
    expect(Filament::getPanel('admin')->getAuthGuard())->toBe('admin')
        ->and(config('auth.guards.admin'))->toBe(['driver' => 'session', 'provider' => 'admins'])
        ->and(config('auth.providers.admins.model'))->toBe(Admin::class);
});

it('is Arabic and right-to-left by default, with an English toggle', function () {
    $admin = AdminPanel::superAdmin();

    $this->actingAs($admin, 'admin')->get('/admin/organizations')
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('المنشآت');

    $this->actingAs($admin, 'admin')
        ->from('/admin/organizations')
        ->get('/admin/locale/en')
        ->assertRedirect('/admin/organizations');

    $this->actingAs($admin, 'admin')->get('/admin/organizations')
        ->assertOk()
        ->assertSee('dir="ltr"', false)
        ->assertSee('Organisations');

    // An outside referrer is not followed.
    $this->actingAs($admin, 'admin')->from('https://elsewhere.example/page')->get('/admin/locale/ar')->assertRedirect('/admin');
    $this->actingAs($admin, 'admin')->get('/admin/organizations')->assertSee('dir="rtl"', false);
});

it('labels the navigation in the admin\'s language', function (string $locale, array $labels) {
    $response = $this->actingAs(AdminPanel::superAdmin(), 'admin')
        ->withSession([AdminLocale::SESSION_KEY => $locale])
        ->get('/admin/competitions')
        ->assertOk();

    foreach ($labels as $label) {
        $response->assertSee($label);
    }
})->with([
    'Arabic' => ['ar', ['العملاء', 'الفوترة', 'المنشآت', 'سجل التدقيق']],
    'English' => ['en', ['Customers', 'Billing', 'Organisations', 'Audit log']],
]);

it('rejects an unknown panel language', function () {
    $this->actingAs(AdminPanel::superAdmin(), 'admin')->get('/admin/locale/fr')->assertNotFound();
});

it('never sends an admin through the organization users\' policies', function () {
    $admin = AdminPanel::superAdmin();
    $competition = Competition::factory()->create();

    expect(Gate::forUser($admin)->allows('view', $competition))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('perm', 'competitions.manage'))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('viewAny', Admin::class))->toBeTrue()
        ->and(Gate::forUser(AdminPanel::operator())->allows('viewAny', Admin::class))->toBeFalse();
});
