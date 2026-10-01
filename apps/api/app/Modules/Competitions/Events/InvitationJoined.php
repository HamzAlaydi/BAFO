<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Support\Auth\Actor;

/**
 * sent or viewed → joined; the participant row exists (ARCHITECTURE §13.11, §10).
 */
final readonly class InvitationJoined
{
    public function __construct(
        public Invitation $invitation,
        public Participant $participant,
        public Actor $actor,
    ) {}
}
