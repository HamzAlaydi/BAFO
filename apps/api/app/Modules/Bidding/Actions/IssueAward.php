<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Actions;

use App\Modules\Bidding\Enums\AwardStatus;
use App\Modules\Bidding\Enums\ErpSyncStatus;
use App\Modules\Bidding\Events\AwardIssued;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Bidding\Services\LiveStateManager;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Contracts\CompetitionStateMachine;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Clock\DbClock;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * T10 closed → awarded (ARCHITECTURE §7.12, API.md `POST …/award`).
 *
 * Guards, in order: status `closed` · the participant has a current, non-voided offer · a
 * non-leading award needs a justification (`not_leading`) · a reserve that the amount does not
 * meet needs `confirm_reserve_not_met` and a justification (`reserve_not_met`).
 *
 * Under the competition lock: the award (amount and offer = the participant's current offer,
 * rank and leading flag from the standing, the ledger head hash), the transition, version++,
 * `AwardIssued` and the `award.issued` audit entry of the issuer's feed. The report is
 * regenerated after commit (QueueCompetitionReport).
 */
final readonly class IssueAward
{
    public function __construct(
        private DbClock $clock,
        private CompetitionStateMachine $stateMachine,
        private LiveStateManager $liveStates,
    ) {}

    public function handle(
        Competition $competition,
        string $participantId,
        ?CloseReason $justificationReason,
        ?string $justificationText,
        bool $confirmReserveNotMet,
        ?string $messageToWinner,
        ?string $internalNotes,
        User $user,
        Actor $actor,
    ): Award {
        return DB::transaction(function () use ($competition, $participantId, $justificationReason, $justificationText, $confirmReserveNotMet, $messageToWinner, $internalNotes, $user, $actor): Award {
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();
            $now = $this->clock->now();

            if ($locked->status !== CompetitionStatus::Closed) {
                throw new ApiException('invalid_state_transition', status: 409, details: [
                    'from' => $locked->status->value,
                    'to' => CompetitionStatus::Awarded->value,
                ]);
            }

            $participant = Participant::query()
                ->where('competition_id', $locked->id)
                ->where('public_id', strtolower($participantId))
                ->first();

            if ($participant === null) {
                throw new ApiException('validation_failed', status: 422, errors: [
                    'participant_id' => [__('bidding.validation.participant_not_found')],
                ]);
            }

            $standing = ParticipantStanding::query()->find($participant->id);

            if ($standing === null || $standing->current_offer_id === null || $standing->current_amount_minor === null) {
                throw new ApiException('award_participant_has_no_offer', 'bidding.errors.award_participant_has_no_offer', 422);
            }

            $amount = $standing->current_amount_minor;
            $reserveMet = LiveStateManager::reserveMet($locked, $amount);

            if (! $standing->is_leader && $justificationReason === null) {
                throw new ApiException('award_justification_required', 'bidding.errors.award_justification_required', 422, details: ['reason' => 'not_leading']);
            }

            if ($reserveMet === false) {
                if (! $confirmReserveNotMet) {
                    throw new ApiException('award_reserve_confirmation_required', 'bidding.errors.award_reserve_confirmation_required', 422);
                }

                if ($justificationReason === null) {
                    throw new ApiException('award_justification_required', 'bidding.errors.award_justification_required', 422, details: ['reason' => 'reserve_not_met']);
                }
            }

            $state = $this->liveStates->forLocked($locked);
            $issuer = Organization::query()->findOrFail($locked->organization_id);

            $award = Award::query()->create([
                'competition_id' => $locked->id,
                'participant_id' => $participant->id,
                'organization_id' => $participant->organization_id,
                'offer_id' => $standing->current_offer_id,
                'amount_minor' => $amount,
                'currency' => $locked->currency,
                'status' => AwardStatus::Issued,
                'is_leading_offer' => $standing->is_leader,
                'rank_at_award' => $standing->rank ?? 0,
                'reserve_met' => $reserveMet,
                'justification_reason_id' => $justificationReason?->id,
                'justification_text' => $justificationReason !== null ? $justificationText : null,
                'message_to_winner' => $messageToWinner,
                'internal_notes' => $internalNotes,
                'awarded_by_user_id' => $user->id,
                'awarded_at' => $now,
                'erp_sync_status' => $issuer->api_enabled ? ErpSyncStatus::Pending : ErpSyncStatus::NotRequired,
                'ledger_head_hash' => (string) $state->ledger_head_hash,
            ]);

            $this->stateMachine->transition($locked, CompetitionStatus::Awarded, $actor, ['awarded_at' => $now]);

            $state->version++;
            $state->save();

            AuditLogger::log('award.issued', $award, meta: [
                'competition_id' => $locked->public_id,
                'participant_id' => $participant->public_id,
                'is_leading_offer' => $award->is_leading_offer,
            ], actor: $actor, organizationId: $locked->organization_id);

            event(new AwardIssued($award, $locked, $actor));

            return $award;
        });
    }
}
