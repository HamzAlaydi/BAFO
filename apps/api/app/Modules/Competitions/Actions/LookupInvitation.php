<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Billing\Enums\Coverage;
use App\Modules\Competitions\Data\InvitationLookup;
use App\Modules\Competitions\Services\InvitationTokens;
use App\Modules\Identity\Models\User;
use App\Support\Auth\Actor;

/**
 * `POST /invitations/lookup` (guest, ARCHITECTURE §13.11): 404 `invitation_invalid` for unknown,
 * revoked or draft invitations; otherwise sent → viewed, and the teaser data. `next_step` is
 * `register` when no user has the invited e-mail, else `login` (`join` is never returned to
 * guests). `sponsored` is true only when the coverage is sponsored.
 */
final readonly class LookupInvitation
{
    public function __construct(
        private InvitationTokens $tokens,
        private MarkInvitationViewed $markViewed,
        private AccessPolicy $access,
    ) {}

    public function handle(string $token, Actor $actor): InvitationLookup
    {
        $invitation = $this->markViewed->handle($this->tokens->find($token), $actor);
        $competition = $invitation->competition()->firstOrFail();
        $invitation->setRelation('competition', $competition);

        $coverage = $this->access->coverageFor([$invitation]);

        return new InvitationLookup(
            invitation: $invitation,
            competition: $competition,
            sponsored: ($coverage[$invitation->id] ?? null) === Coverage::Sponsored,
            nextStep: User::query()->where('email', $invitation->email)->exists() ? 'login' : 'register',
        );
    }
}
