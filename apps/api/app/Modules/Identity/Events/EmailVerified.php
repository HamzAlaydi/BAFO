<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use App\Modules\Identity\Models\User;

/**
 * A user proved their e-mail with the verification OTP (ARCHITECTURE §10). Competitions attaches
 * pending invitations to the user's organization; Integrations links vendors.
 */
final readonly class EmailVerified
{
    public function __construct(
        public User $user,
    ) {}
}
