<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\OtpCode;
use Illuminate\Console\Scheduling\Schedule;

it('deletes old OTP codes and spends expired invitation tokens', function () {
    $old = OtpCode::factory()->create();
    $this->travel(25)->hours();
    $recent = OtpCode::factory()->create();
    $expired = Membership::factory()->invited()->create(['invite_expires_at' => now()->subMinute()]);
    $valid = Membership::factory()->invited()->create();

    $this->artisan('identity:prune')->assertSuccessful();

    expect(OtpCode::query()->pluck('id')->all())->toBe([$recent->id])
        ->and(OtpCode::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and($expired->refresh()->invite_token_hash)->toBeNull()
        ->and($expired->status)->toBe(MembershipStatus::Invited)
        ->and($valid->refresh()->invite_token_hash)->not->toBeNull();
});

it('schedules the Identity commands', function () {
    $events = collect(app(Schedule::class)->events())->mapWithKeys(fn ($event): array => [(string) $event->command => $event->expression]);

    $prune = $events->first(fn (string $expression, string $command): bool => str_contains($command, 'identity:prune'));
    $deletions = $events->first(fn (string $expression, string $command): bool => str_contains($command, 'identity:execute-account-deletions'));

    expect($prune)->toBe('0 0 * * *')
        ->and($deletions)->toBe('0 * * * *');
});
