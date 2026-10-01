<?php

declare(strict_types=1);

use App\Modules\Catalog\Database\Seeders\CatalogReferenceSeeder;
use App\Modules\Identity\Database\Seeders\IdentityDemoSeeder;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Database\Seeders\PlatformReferenceSeeder;
use Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->seed([PlatformReferenceSeeder::class, CatalogReferenceSeeder::class, IdentityDemoSeeder::class]);
});

it('is picked up by the DemoSeeder', function () {
    expect(DemoSeeder::demoSeeders())->toContain(IdentityDemoSeeder::class);
});

it('creates the demo organizations with their flags and complete billing profiles', function () {
    expect(Organization::query()->count())->toBe(5);

    $issuer = Organization::query()->where('cr_number', '1010000001')->sole();

    expect($issuer)
        ->name->toBe('Issuer Co')
        ->api_enabled->toBeTrue()
        ->auction_enabled->toBeTrue()
        ->sponsorship_enabled->toBeTrue()
        ->and($issuer->isBillingProfileComplete())->toBeTrue()
        ->and($issuer->region->code)->toBe('RIY')
        ->and($issuer->categories)->not->toBeEmpty()
        ->and(Organization::query()->where('cr_number', '1010000002')->sole()->region->code)->toBe('MAK');
});

it('creates the demo users with their roles and flags, verified', function () {
    expect(User::query()->count())->toBe(7);

    foreach (DemoSeeder::ORGANIZATIONS as $key => $organization) {
        foreach (array_keys($organization['users']) as $userKey) {
            $definition = DemoSeeder::user($key.'.'.$userKey);
            $user = User::query()->where('email', $definition['email'])->sole();

            expect($user->hasVerifiedEmail())->toBeTrue()
                ->and($user->membership->role->value)->toBe($definition['role'])
                ->and($user->membership->status)->toBe(MembershipStatus::Active)
                ->and($user->membership->can_award)->toBe($definition['can_award'])
                ->and($user->membership->can_purchase)->toBe($definition['can_purchase'])
                ->and($user->membership->organization->cr_number)->toBe($organization['cr_number']);
        }
    }

    expect(Consent::query()->count())->toBe(14);
});

it('lets the demo users sign in with the demo password', function () {
    $this->postJson('/api/app/v1/auth/login', [
        'email' => DemoSeeder::user('issuer.admin')['email'],
        'password' => DemoSeeder::PASSWORD,
        'device_name' => 'demo',
    ])
        ->assertOk()
        ->assertJsonPath('data.membership.role', 'admin')
        ->assertJsonPath('data.organization.name', 'Issuer Co');
});
