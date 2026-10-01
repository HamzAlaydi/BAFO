<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use App\Modules\Identity\Models\Membership;
use App\Support\Auth\Actor;

/**
 * A membership changed: role, flags or status, including an accepted invitation
 * (ARCHITECTURE §10, §13.10).
 */
final readonly class MemberUpdated
{
    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    public function __construct(
        public Membership $membership,
        public Actor $actor,
        public array $changes,
    ) {}
}
