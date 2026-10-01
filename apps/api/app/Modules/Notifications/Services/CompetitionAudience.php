<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Data\ParticipantRecipient;
use Illuminate\Database\Eloquent\Collection;

/**
 * The competition recipient sets of ARCHITECTURE §11.1: the issuer team (active members of the
 * issuer organization) and the participant users (active members of each participant
 * organization).
 */
final readonly class CompetitionAudience
{
    public function __construct(private Recipients $recipients) {}

    /**
     * @return Collection<int, User>
     */
    public function issuerTeam(Competition $competition): Collection
    {
        return $this->recipients->members($competition->organization_id);
    }

    /**
     * The users of the given participants (default: every participant of the competition).
     *
     * @param  iterable<Participant>|null  $participants
     * @return list<ParticipantRecipient>
     */
    public function participantUsers(Competition $competition, ?iterable $participants = null): array
    {
        $rows = [];

        foreach ($participants ?? $competition->participants()->orderBy('id')->get() as $participant) {
            $rows[] = $participant;
        }

        $users = $this->recipients->membersByOrganization(
            array_map(static fn (Participant $participant): int => $participant->organization_id, $rows),
        );

        $recipients = [];

        foreach ($rows as $participant) {
            foreach ($users[$participant->organization_id] ?? [] as $user) {
                $recipients[] = new ParticipantRecipient($participant, $user);
            }
        }

        return $recipients;
    }

    /**
     * The template parameters every competition notification carries.
     *
     * @return array{competition_title: string, direction: string}
     */
    public static function params(Competition $competition): array
    {
        return [
            'competition_title' => $competition->title,
            'direction' => $competition->direction->value,
        ];
    }
}
