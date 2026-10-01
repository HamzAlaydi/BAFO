<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Actions;

use App\Modules\Bidding\Enums\BafoRoundStatus;
use App\Modules\Bidding\Events\BafoRoundStarted;
use App\Modules\Bidding\Jobs\EndBafoRound;
use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Bidding\Services\LiveStateManager;
use App\Modules\Competitions\Contracts\CompetitionStateMachine;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Clock\DbClock;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * T7 closed → bafo_round (ARCHITECTURE §7.11): the issuer invites a manual shortlist to one
 * best-and-final offer each. At most one round per competition (D7).
 *
 * Guards: status `closed` · `bafo_round_enabled` · no round yet · every id is a joined
 * participant with a current, non-voided offer. Under the competition lock: the round
 * (`cutoff_at = now + duration`), the shortlisted standings with their reference amount, the
 * transition, version++ and `BafoRoundStarted`. After commit, `EndBafoRound` is dispatched with
 * the cutoff as its delay (the `bidding:tick` safety net covers a lost job).
 */
final readonly class StartBafoRound
{
    public function __construct(
        private DbClock $clock,
        private CompetitionStateMachine $stateMachine,
        private LiveStateManager $liveStates,
    ) {}

    /**
     * @param  list<string>  $participantIds  participant public ids
     */
    public function handle(Competition $competition, array $participantIds, ?int $durationMinutes, User $user, Actor $actor): BafoRound
    {
        $round = DB::transaction(function () use ($competition, $participantIds, $durationMinutes, $user, $actor): BafoRound {
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();
            $now = $this->clock->now();

            if ($locked->status !== CompetitionStatus::Closed) {
                throw new ApiException('invalid_state_transition', status: 409, details: [
                    'from' => $locked->status->value,
                    'to' => CompetitionStatus::BafoRound->value,
                ]);
            }

            if (! $locked->bafo_round_enabled) {
                throw new ApiException('bafo_not_enabled', 'bidding.errors.bafo_not_enabled', 409);
            }

            if (BafoRound::query()->where('competition_id', $locked->id)->exists()) {
                throw new ApiException('bafo_already_used', 'bidding.errors.bafo_already_used', 409);
            }

            $shortlist = $this->shortlist($locked, $participantIds);
            $duration = $durationMinutes ?? $locked->bafo_duration_minutes ?? 60;

            $round = BafoRound::query()->create([
                'competition_id' => $locked->id,
                'started_by_user_id' => $user->id,
                'status' => BafoRoundStatus::Running,
                'starts_at' => $now,
                'cutoff_at' => $now->addMinutes($duration),
                'shortlist_count' => count($shortlist),
            ]);

            foreach ($shortlist as $standing) {
                $standing->bafo_shortlisted = true;
                $standing->bafo_reference_amount_minor = $standing->current_amount_minor;
                $standing->save();
            }

            $this->stateMachine->transition($locked, CompetitionStatus::BafoRound, $actor);

            $state = $this->liveStates->forLocked($locked);
            $state->version++;
            $state->save();

            AuditLogger::log('bafo_round.started', $round, meta: [
                'participant_ids' => array_map(static fn (string $id): string => strtolower($id), $participantIds),
                'duration_minutes' => $duration,
            ], actor: $actor, organizationId: $locked->organization_id);

            event(new BafoRoundStarted($round, $locked, $actor));

            return $round;
        });

        EndBafoRound::dispatch($competition->id)->delay($round->cutoff_at)->afterCommit();

        return $round;
    }

    /**
     * The standings of the shortlisted participants; 422 `bafo_shortlist_invalid` listing every
     * id that is not a joined participant with a current, non-voided offer.
     *
     * @param  list<string>  $participantIds
     * @return list<ParticipantStanding>
     */
    private function shortlist(Competition $competition, array $participantIds): array
    {
        $ids = array_values(array_unique(array_map(static fn (string $id): string => strtolower($id), $participantIds)));

        $participants = Participant::query()
            ->where('competition_id', $competition->id)
            ->whereIn('public_id', $ids)
            ->get()
            ->keyBy('public_id');

        $standings = ParticipantStanding::query()
            ->whereIn('participant_id', $participants->pluck('id')->all())
            ->whereNotNull('current_offer_id')
            ->get()
            ->keyBy('participant_id');

        $valid = [];
        $invalid = [];

        foreach ($ids as $id) {
            $participant = $participants->get($id);
            $standing = $participant !== null ? $standings->get($participant->id) : null;

            if ($standing === null) {
                $invalid[] = $id;
            } else {
                $valid[] = $standing;
            }
        }

        if ($invalid !== [] || $valid === []) {
            throw new ApiException('bafo_shortlist_invalid', 'bidding.errors.bafo_shortlist_invalid', 422, details: [
                'invalid_participant_ids' => $invalid,
            ]);
        }

        return $valid;
    }
}
