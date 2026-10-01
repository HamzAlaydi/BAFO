<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\ExtensionKind;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionExtension;
use App\Modules\Competitions\Services\StateMachine;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Clock\DbClock;
use Illuminate\Support\Facades\DB;

/**
 * Platform admin force-close (ARCHITECTURE §6.1 T5, §16): `effective_close_at` becomes now,
 * recorded as an `admin` extension row with the reason, then the competition closes as at a
 * normal deadline. Called by the Admin panel with `Actor::forAdmin()`.
 *
 * CONTRACT-GAP: the extension row is written directly (no CompetitionExtended event), because the
 * close is brought forward, not extended; participants are told through CompetitionClosed.
 */
final readonly class ForceCloseCompetition
{
    public function __construct(
        private DbClock $clock,
        private CloseDueCompetition $close,
    ) {}

    public function handle(Competition $competition, string $reason, Actor $actor): Competition
    {
        DB::transaction(function () use ($competition, $reason, $actor): void {
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CompetitionStatus::Live) {
                throw StateMachine::invalid($locked->status, CompetitionStatus::Closed);
            }

            $now = $this->clock->now();

            CompetitionExtension::query()->create([
                'competition_id' => $locked->id,
                'kind' => ExtensionKind::Admin,
                'previous_close_at' => $locked->effective_close_at ?? $now,
                'new_close_at' => $now,
                'actor_user_id' => $actor->userId,
                'actor_admin_id' => $actor->adminId,
                'reason' => $reason,
            ]);

            $locked->effective_close_at = $now;
            $locked->save();

            AuditLogger::log('competition.force_closed', $locked, meta: ['reason' => $reason], actor: $actor,
                organizationId: $locked->organization_id);

            $this->close->handle($locked->id, $actor);
        });

        return $competition->refresh();
    }
}
