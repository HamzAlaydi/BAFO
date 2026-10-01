<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Events\CompetitionFinalWindowStarted;
use App\Modules\Competitions\Models\Competition;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Stamps `final_window_started_at` once the final pricing window of a live competition is due
 * (ARCHITECTURE §12 tick step 2). The phase itself is derived from the clock (§6.1); the stamp
 * only announces it once.
 */
final class StartFinalWindow
{
    public function handle(int $competitionId): bool
    {
        return DB::transaction(static function () use ($competitionId): bool {
            $competition = Competition::query()->whereKey($competitionId)->lockForUpdate()->first();
            $now = Date::now();

            if ($competition === null
                || $competition->status !== CompetitionStatus::Live
                || $competition->final_window_started_at !== null
                || $competition->final_window_starts_at === null
                || $competition->final_window_starts_at->greaterThan($now)) {
                return false;
            }

            $competition->final_window_started_at = $now;
            $competition->save();

            AuditLogger::log('competition.final_window_started', $competition, actor: Actor::system(),
                organizationId: $competition->organization_id);

            event(new CompetitionFinalWindowStarted($competition));

            return true;
        });
    }
}
