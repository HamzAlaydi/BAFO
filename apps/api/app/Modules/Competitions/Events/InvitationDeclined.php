<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\Invitation;

/**
 * sent or viewed → declined (ARCHITECTURE §6.2, §10). Billing releases the pass synchronously.
 */
final readonly class InvitationDeclined
{
    public function __construct(
        public Invitation $invitation,
    ) {}
}
