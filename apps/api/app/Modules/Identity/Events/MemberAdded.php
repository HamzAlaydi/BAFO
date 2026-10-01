<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use App\Modules\Identity\Models\Membership;
use App\Support\Auth\Actor;

/**
 * A team member was invited into an organization (ARCHITECTURE §10, §13.10).
 */
final readonly class MemberAdded
{
    public function __construct(
        public Membership $membership,
        public Actor $actor,
    ) {}
}
