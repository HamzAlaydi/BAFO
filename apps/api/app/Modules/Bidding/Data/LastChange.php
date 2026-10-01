<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Data;

use App\Modules\Bidding\Enums\LiveChangeKind;

/**
 * `last_change` of a live snapshot (API.md §2.8): the kind of change and, for an extension,
 * its kind (`auto`, `manual`, `admin`).
 */
final readonly class LastChange
{
    public function __construct(
        public LiveChangeKind $kind,
        public ?string $reason = null,
    ) {}

    public static function of(LiveChangeKind $kind, ?string $reason = null): self
    {
        return new self($kind, $reason);
    }

    public static function snapshot(): self
    {
        return new self(LiveChangeKind::Snapshot);
    }

    /**
     * @return array{kind: string, reason: string|null}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind->value, 'reason' => $this->reason];
    }
}
