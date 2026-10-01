<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Broadcasting\Channels;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Str;

/**
 * `private-competition.{competitionPublicId}.participant.{organizationPublicId}` (ARCHITECTURE
 * §9.2, D9): one channel per participant organization. Authorised when the organization is the
 * user's own (active membership) and it joined the competition.
 */
final class CompetitionParticipantChannel
{
    public function join(User $user, string $competition, string $organization): bool
    {
        $organizationId = ChannelMembership::organizationId($user);

        if ($organizationId === null || ! Str::isUlid($competition) || ! Str::isUlid($organization)) {
            return false;
        }

        $ownPublicId = Organization::query()->whereKey($organizationId)->value('public_id');

        if (! is_string($ownPublicId) || $ownPublicId !== strtolower($organization)) {
            return false;
        }

        return Participant::query()
            ->where('organization_id', $organizationId)
            ->whereIn('competition_id', Competition::query()->select('id')->where('public_id', strtolower($competition)))
            ->exists();
    }
}
