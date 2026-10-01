<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Webhooks;

/**
 * The outcome of one delivery attempt: the HTTP status (null on a network error, timeout or
 * blocked target), the error text, the response excerpt and the duration.
 */
final readonly class AttemptResult
{
    public function __construct(
        public ?int $status,
        public ?string $error,
        public ?string $excerpt,
        public int $durationMs,
    ) {}

    public function succeeded(): bool
    {
        return $this->status !== null && $this->status >= 200 && $this->status < 300;
    }
}
