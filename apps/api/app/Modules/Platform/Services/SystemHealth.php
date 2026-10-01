<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use Closure;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Database\DatabaseManager;
use Throwable;

/**
 * Probes the services the API cannot work without (PostgreSQL, Redis, the queue).
 * Failures are reported to the log, never echoed to the client.
 */
final readonly class SystemHealth
{
    public const string OK = 'ok';

    public const string DOWN = 'down';

    public const string DEGRADED = 'degraded';

    public function __construct(
        private DatabaseManager $database,
        private RedisFactory $redis,
        private QueueFactory $queue,
    ) {}

    /**
     * The health body of API.md §1.1: {"status": "ok", "checks": {"database": "ok", "redis": "ok", "queue": "ok"}}.
     *
     * @return array{status: string, checks: array{database: string, redis: string, queue: string}}
     */
    public function report(): array
    {
        $checks = [
            'database' => $this->probe(fn (): mixed => $this->database->connection()->selectOne('select 1 as ok')),
            'redis' => $this->probe(fn (): mixed => $this->redis->connection()->command('ping')),
            // CONTRACT-GAP: "queue" means the default queue connection answers (it reads the
            // size of the default queue); worker liveness is not probed.
            'queue' => $this->probe(fn (): int => $this->queue->connection()->size()),
        ];

        $healthy = array_all($checks, static fn (string $status): bool => $status === self::OK);

        return [
            'status' => $healthy ? self::OK : self::DEGRADED,
            'checks' => $checks,
        ];
    }

    /**
     * @param  Closure(): mixed  $probe
     */
    private function probe(Closure $probe): string
    {
        try {
            $probe();

            return self::OK;
        } catch (Throwable $e) {
            report($e);

            return self::DOWN;
        }
    }
}
