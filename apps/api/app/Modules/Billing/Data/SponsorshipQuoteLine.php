<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use App\Modules\Billing\Enums\Coverage;
use App\Modules\Billing\Enums\QuoteLineReason;

/**
 * One invitation (or new invitation row) of a sponsorship quote (API.md §2.10). The
 * invitation id is null for rows that do not exist yet (invite quotes).
 */
final readonly class SponsorshipQuoteLine
{
    public function __construct(
        public ?string $invitationId,
        public string $email,
        public ?string $organizationName,
        public Coverage $coverage,
        public ?QuoteLineReason $reason,
    ) {}

    /**
     * @return array{invitation_id: string|null, email: string, organization_name: string|null, coverage: string, reason: string|null}
     */
    public function toArray(): array
    {
        return [
            'invitation_id' => $this->invitationId,
            'email' => $this->email,
            'organization_name' => $this->organizationName,
            'coverage' => $this->coverage->value,
            'reason' => $this->reason?->value,
        ];
    }
}
