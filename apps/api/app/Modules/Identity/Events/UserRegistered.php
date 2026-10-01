<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;

/**
 * A company registered: its organization, owner user and membership exist; the e-mail is not
 * verified yet (ARCHITECTURE §10, §13.9). The OTP mail is sent by the Action.
 */
final readonly class UserRegistered
{
    public function __construct(
        public User $user,
        public Organization $organization,
    ) {}
}
