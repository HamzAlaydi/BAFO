<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes a draft (ARCHITECTURE §6.1 "Deleting a draft"; not a transition). A published
 * competition is cancelled instead: 409 `competition_not_editable`.
 */
final class DeleteDraftCompetition
{
    public function handle(Competition $competition, Actor $actor): void
    {
        DB::transaction(static function () use ($competition, $actor): void {
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CompetitionStatus::Draft) {
                throw UpdateCompetition::notEditable();
            }

            $locked->delete();

            AuditLogger::log('competition.deleted', $locked, actor: $actor, organizationId: $locked->organization_id);
        });
    }
}
