<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Actions;

use App\Modules\Bidding\Enums\AwardStatus;
use App\Modules\Bidding\Events\AwardRevoked;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Services\LiveStateManager;
use App\Modules\Competitions\Contracts\CompetitionStateMachine;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Clock\DbClock;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * T11 awarded → closed (ARCHITECTURE §7.12 "Revoke"): the award row becomes `revoked` with the
 * reason, the competition is back in evaluation, and a new award is a new row (§6.6).
 */
final readonly class RevokeAward
{
    public function __construct(
        private DbClock $clock,
        private CompetitionStateMachine $stateMachine,
        private LiveStateManager $liveStates,
    ) {}

    public function handle(Competition $competition, string $reason, User $user, Actor $actor): Award
    {
        return DB::transaction(function () use ($competition, $reason, $user, $actor): Award {
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();
            $now = $this->clock->now();

            $award = Award::query()
                ->where('competition_id', $locked->id)
                ->where('status', AwardStatus::Issued->value)
                ->lockForUpdate()
                ->first();

            if ($locked->status !== CompetitionStatus::Awarded || $award === null) {
                throw new ApiException('invalid_state_transition', status: 409, details: [
                    'from' => $locked->status->value,
                    'to' => CompetitionStatus::Closed->value,
                ]);
            }

            $award->forceFill([
                'status' => AwardStatus::Revoked,
                'revoked_by_user_id' => $user->id,
                'revoked_at' => $now,
                'revoke_reason' => $reason,
            ])->save();

            $this->stateMachine->transition($locked, CompetitionStatus::Closed, $actor);

            $state = $this->liveStates->forLocked($locked);
            $state->version++;
            $state->save();

            AuditLogger::log('award.revoked', $award, meta: ['reason' => $reason], actor: $actor, organizationId: $locked->organization_id);

            event(new AwardRevoked($award, $locked, $actor));

            return $award;
        });
    }
}
