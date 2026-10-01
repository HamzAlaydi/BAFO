<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Broadcasting\Channels;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Str;

/**
 * `private-competition.{competitionPublicId}` (ARCHITECTURE §9.2): the issuer side. Authorised
 * when the user's (active) organization issued the competition.
 */
final class CompetitionIssuerChannel
{
    public function join(User $user, string $competition): bool
    {
        $organizationId = ChannelMembership::organizationId($user);

        return $organizationId !== null
            && Str::isUlid($competition)
            && Competition::query()
                ->where('public_id', strtolower($competition))
                ->where('organization_id', $organizationId)
                ->exists();
    }
}
