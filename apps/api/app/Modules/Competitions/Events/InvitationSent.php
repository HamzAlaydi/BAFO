<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\Invitation;
use App\Support\Auth\Actor;

/**
 * An invitation moved to `sent` (publish, invite on a published competition) or its token was re-issued (resend) (ARCHITECTURE §6.2, §10).
 */
final readonly class InvitationSent
{
    public function __construct(
        public Invitation $invitation,
        public Actor $actor,
    ) {}
}
