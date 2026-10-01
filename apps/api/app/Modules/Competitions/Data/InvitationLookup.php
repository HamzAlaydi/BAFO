<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Data;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;

/**
 * The result of a guest token lookup (API.md §1.5 `InvitationLookup`): the invitation (now at
 * least viewed), its competition, whether the fees are covered, and the next step of the
 * invitee (`register` or `login`).
 */
final readonly class InvitationLookup
{
    public function __construct(
        public Invitation $invitation,
        public Competition $competition,
        public bool $sponsored,
        public string $nextStep,
    ) {}
}
