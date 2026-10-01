<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Broadcasting\Channels;

use Illuminate\Support\Facades\Broadcast;

/**
 * The Bidding channels of ARCHITECTURE §9.2 (Echo `private()` names; Pusher adds `private-`):
 *
 *   competition.{competitionPublicId}                                    the issuer side
 *   competition.{competitionPublicId}.participant.{organizationPublicId} one per participant organization
 *
 * There are no presence channels and no shared participant room (D9).
 */
final class BiddingChannels
{
    public const string ISSUER = 'competition.{competition}';

    public const string PARTICIPANT = 'competition.{competition}.participant.{organization}';

    public static function register(): void
    {
        Broadcast::channel(self::ISSUER, CompetitionIssuerChannel::class);
        Broadcast::channel(self::PARTICIPANT, CompetitionParticipantChannel::class);
    }
}
