<?php

declare(strict_types=1);

use App\Modules\Platform\Services\SystemHealth;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

it('reports the database, redis and queue checks', function () {
    $this->getJson('/api/app/v1/health')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data', [
            'status' => 'ok',
            'checks' => ['database' => 'ok', 'redis' => 'ok', 'queue' => 'ok'],
        ])
        ->assertJsonStructure(['meta' => ['server_time']]);
});

it('is reachable without a token', function () {
    $this->getJson('/api/app/v1/health')->assertOk();
});

it('answers 503 with the same shape when a dependency is down', function () {
    $redis = Mockery::mock(RedisFactory::class);
    $redis->shouldReceive('connection')->andThrow(new RuntimeException('redis unreachable'));
    $this->app->instance(RedisFactory::class, $redis);
    $this->app->forgetInstance(SystemHealth::class);

    $this->getJson('/api/app/v1/health')
        ->assertStatus(503)
        ->assertJsonPath('data', [
            'status' => 'degraded',
            'checks' => ['database' => 'ok', 'redis' => 'down', 'queue' => 'ok'],
        ]);
});
