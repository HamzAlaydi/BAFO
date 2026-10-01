<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminReferenceSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\Admin;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;

/*
 * ARCHITECTURE §17 step 4: one super_admin from ADMIN_SEED_EMAIL / ADMIN_SEED_PASSWORD, else the
 * DemoSeeder credentials in local and testing only. Idempotent.
 */

it('is one of the reference seeders DatabaseSeeder runs', function () {
    expect(DatabaseSeeder::referenceSeeders())->toContain(AdminReferenceSeeder::class);
});

it('seeds the demo super admin when no credentials are configured', function () {
    config(['bafo.admin.seed.email' => null, 'bafo.admin.seed.password' => null]);

    $this->seed(AdminReferenceSeeder::class);

    $admin = Admin::query()->where('email', DemoSeeder::ADMIN_EMAIL)->sole();
    expect($admin->role)->toBe(AdminRole::SuperAdmin)
        ->and($admin->is_active)->toBeTrue()
        ->and(Hash::check(DemoSeeder::ADMIN_PASSWORD, $admin->password))->toBeTrue();
});

it('uses ADMIN_SEED_EMAIL and ADMIN_SEED_PASSWORD when set', function () {
    config(['bafo.admin.seed.email' => 'Root@Example.com', 'bafo.admin.seed.password' => 'a-configured-secret']);

    $this->seed(AdminReferenceSeeder::class);

    $admin = Admin::query()->sole();
    expect($admin->email)->toBe('root@example.com')
        ->and(Hash::check('a-configured-secret', $admin->password))->toBeTrue();
});

it('keeps an existing admin as it is on a re-seed', function () {
    config(['bafo.admin.seed.email' => null, 'bafo.admin.seed.password' => null]);
    $this->seed(AdminReferenceSeeder::class);
    Admin::query()->update(['password' => Hash::make('changed-in-the-panel')]);

    $this->seed(AdminReferenceSeeder::class);

    expect(Admin::query()->count())->toBe(1)
        ->and(Hash::check('changed-in-the-panel', Admin::query()->sole()->password))->toBeTrue();
});

it('seeds nothing outside local and testing without configured credentials', function () {
    config(['bafo.admin.seed.email' => null, 'bafo.admin.seed.password' => null]);
    app()->detectEnvironment(fn (): string => 'production');

    try {
        expect(AdminReferenceSeeder::credentials())->toBeNull();
        app(AdminReferenceSeeder::class)->run();
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }

    expect(Admin::query()->count())->toBe(0);
});
