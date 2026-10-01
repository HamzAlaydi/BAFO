<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\Competition;

/**
 * sent or viewed → expired, at the cut-off, the close or the cancellation (ARCHITECTURE §6.2, §10).
 */
final readonly class InvitationsExpired
{
    /**
     * @param  list<int>  $invitationIds
     */
    public function __construct(
        public Competition $competition,
        public array $invitationIds,
    ) {}
}
