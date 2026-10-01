<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\Invitation;

/**
 * sent → viewed (ARCHITECTURE §6.2, §10).
 */
final readonly class InvitationViewed
{
    public function __construct(
        public Invitation $invitation,
    ) {}
}
