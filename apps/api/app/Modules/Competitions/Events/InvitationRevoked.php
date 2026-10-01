<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\Invitation;
use App\Support\Auth\Actor;

/**
 * sent or viewed → revoked, by the issuer, an admin or a duplicate organization at join (ARCHITECTURE §6.2, §13.5, §10). Billing releases the pass synchronously.
 */
final readonly class InvitationRevoked
{
    public function __construct(
        public Invitation $invitation,
        public Actor $actor,
    ) {}
}
