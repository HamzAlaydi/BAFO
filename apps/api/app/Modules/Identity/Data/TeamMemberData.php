<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

use App\Modules\Identity\Enums\OrgRole;

/**
 * Validated `POST /team/members` input (API.md §1.3, ARCHITECTURE §13.10).
 */
final readonly class TeamMemberData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
        public OrgRole $role,
        public ?bool $canAward,
        public ?bool $canPurchase,
    ) {}
}
