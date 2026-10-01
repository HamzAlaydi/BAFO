<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Contracts\CompetitionStateMachine;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Events\CompetitionOpened;
use App\Modules\Competitions\Models\Competition;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * T3 scheduled → live when `bidding_opens_at` is reached (ARCHITECTURE §6.1, §12 tick step 1).
 * Idempotent: a competition that is no longer scheduled, or not due, is left alone.
 */
final readonly class OpenCompetition
{
    public function __construct(private CompetitionStateMachine $stateMachine) {}

    public function handle(int $competitionId): bool
    {
        return DB::transaction(function () use ($competitionId): bool {
            $competition = Competition::query()->whereKey($competitionId)->lockForUpdate()->first();
            $now = Date::now();

            if ($competition === null
                || $competition->status !== CompetitionStatus::Scheduled
                || $competition->bidding_opens_at === null
                || $competition->bidding_opens_at->greaterThan($now)) {
                return false;
            }

            $actor = Actor::system();

            $this->stateMachine->transition($competition, CompetitionStatus::Live, $actor);

            AuditLogger::log('competition.opened', $competition, actor: $actor, organizationId: $competition->organization_id);

            event(new CompetitionOpened($competition));

            return true;
        });
    }
}
