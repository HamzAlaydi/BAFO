<?php

declare(strict_types=1);

use App\Modules\Billing\Models\Subscription;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Database\Seeders\IdentityDemoSeeder;
use Illuminate\Support\Facades\Artisan;

it('completes a partially seeded demo and is idempotent', function (): void {
    $this->seed();
    $this->seed(IdentityDemoSeeder::class);

    expect(Subscription::query()->count())->toBe(0)
        ->and(Competition::query()->count())->toBe(0);

    expect(Artisan::call('demo:ensure'))->toBe(0);

    $subscriptions = Subscription::query()->count();
    $competitions = Competition::query()->count();

    expect($subscriptions)->toBe(4)
        ->and($competitions)->toBeGreaterThan(0);

    expect(Artisan::call('demo:ensure'))->toBe(0);

    expect(Subscription::query()->count())->toBe($subscriptions)
        ->and(Competition::query()->count())->toBe($competitions);
});

it('refuses to run in production', function (): void {
    app()->detectEnvironment(static fn (): string => 'production');

    expect(Artisan::call('demo:ensure'))->toBe(1);
});
