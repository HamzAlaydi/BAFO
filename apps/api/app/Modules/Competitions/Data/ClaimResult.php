<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Data;

use App\Modules\Competitions\Models\Invitation;
use Carbon\CarbonImmutable;

/**
 * The outcome of an invitation claim (API.md §1.5): the bound invitation (200), or the masked
 * address an OTP was sent to (202).
 */
final readonly class ClaimResult
{
    public function __construct(
        public ?Invitation $invitation,
        public ?string $otpSentTo = null,
        public ?CarbonImmutable $otpExpiresAt = null,
    ) {}

    public function isBound(): bool
    {
        return $this->invitation !== null;
    }
}
