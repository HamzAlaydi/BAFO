<?php

declare(strict_types=1);

use App\Support\Idempotency\IdempotencyKey;
use Illuminate\Console\Scheduling\Schedule;

function platformIdempotencyRow(string $key, DateTimeInterface $expiresAt): void
{
    IdempotencyKey::query()->insert([
        'scope_type' => 'user',
        'scope_id' => 1,
        'key' => $key,
        'method' => 'POST',
        'path' => '/api/public/v1/vendors',
        'request_hash' => str_repeat('a', 64),
        'response_status' => 201,
        'response_body' => '{}',
        'expires_at' => $expiresAt,
        'created_at' => now(),
    ]);
}

it('deletes expired idempotency keys only', function () {
    platformIdempotencyRow('expired-key-1', now()->subMinute());
    platformIdempotencyRow('live-key-0001', now()->addHour());

    $this->artisan('platform:prune')
        ->expectsOutputToContain('Deleted 1 expired idempotency keys.')
        ->assertSuccessful();

    expect(IdempotencyKey::query()->pluck('key')->all())->toBe(['live-key-0001']);
});

it('is scheduled daily, with the Horizon snapshot every five minutes', function () {
    $events = collect(app(Schedule::class)->events());

    $prune = $events->first(fn ($event) => str_contains((string) $event->command, 'platform:prune'));
    $snapshot = $events->first(fn ($event) => str_contains((string) $event->command, 'horizon:snapshot'));

    expect($prune?->expression)->toBe('0 0 * * *')
        ->and($snapshot?->expression)->toBe('*/5 * * * *');
});
