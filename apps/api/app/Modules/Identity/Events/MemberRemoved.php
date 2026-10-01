<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use App\Modules\Identity\Models\Membership;
use App\Support\Auth\Actor;

/**
 * A team member was removed: the membership row is deleted and the user anonymised
 * (ARCHITECTURE §10, §13.10). `$membership` is the deleted model.
 */
final readonly class MemberRemoved
{
    public function __construct(
        public Membership $membership,
        public Actor $actor,
    ) {}
}
