<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Data;

use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\User;

/**
 * A user of a participant organization, with the participant row (for heartbeat checks).
 */
final readonly class ParticipantRecipient
{
    public function __construct(
        public Participant $participant,
        public User $user,
    ) {}
}
