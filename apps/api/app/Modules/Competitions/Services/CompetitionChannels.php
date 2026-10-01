<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;

/**
 * Realtime channel names (ARCHITECTURE D9, §9.2), without the `private-` prefix. Bidding
 * registers the channel authorisation callbacks.
 *
 *   competition.{competitionPublicId}                                       issuer side
 *   competition.{competitionPublicId}.participant.{organizationPublicId}    one per participant
 */
final class CompetitionChannels
{
    public static function issuer(Competition $competition): string
    {
        return 'competition.'.$competition->public_id;
    }

    public static function participant(Competition $competition, string $organizationPublicId): string
    {
        return 'competition.'.$competition->public_id.'.participant.'.$organizationPublicId;
    }

    /**
     * The issuer channel and every participant channel.
     *
     * @return list<string>
     */
    public static function all(Competition $competition): array
    {
        $channels = [self::issuer($competition)];

        $participants = Participant::query()->with('organization')->where('competition_id', $competition->id)->get();

        foreach ($participants as $participant) {
            $channels[] = self::participant($competition, $participant->organization->public_id);
        }

        return $channels;
    }
}
