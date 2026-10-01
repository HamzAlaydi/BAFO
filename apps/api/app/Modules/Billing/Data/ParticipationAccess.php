<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use App\Modules\Billing\Enums\AccessState;
use App\Modules\Billing\Enums\Coverage;
use App\Support\Http\Iso;
use Carbon\CarbonImmutable;

/**
 * An invitee organization's access to a competition (ARCHITECTURE §8.4), rendered as the
 * `access` object of API.md §2.6: `{state, coverage, sponsor_name, join_deadline}`. Apps render
 * only from these values; they never compute entitlement.
 */
final readonly class ParticipationAccess
{
    public function __construct(
        public AccessState $state,
        public Coverage $coverage,
        public ?string $sponsorName,
        public ?CarbonImmutable $joinDeadline,
    ) {}

    /**
     * @return array{state: string, coverage: string, sponsor_name: string|null, join_deadline: string|null}
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'coverage' => $this->coverage->value,
            'sponsor_name' => $this->sponsorName,
            'join_deadline' => Iso::format($this->joinDeadline),
        ];
    }
}
