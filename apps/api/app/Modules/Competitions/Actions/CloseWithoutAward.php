<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Contracts\CompetitionStateMachine;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Events\CompetitionClosedWithoutAward;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\CloseReasonGuard;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * T12: closed → not_awarded (ARCHITECTURE §7.12), with a reason of kind `not_awarded`.
 */
final readonly class CloseWithoutAward
{
    public function __construct(private CompetitionStateMachine $stateMachine) {}

    public function handle(Competition $competition, CloseReason $reason, ?string $note, Actor $actor): Competition
    {
        CloseReasonGuard::assert($reason, CloseReasonKind::NotAwarded, $note);

        return DB::transaction(function () use ($competition, $reason, $note, $actor): Competition {
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->transition($locked, CompetitionStatus::NotAwarded, $actor, [
                'not_awarded_reason_id' => $reason->id,
                'not_awarded_note' => CloseReasonGuard::cleanNote($note),
            ]);

            AuditLogger::log('competition.not_awarded', $locked, meta: ['reason' => $reason->code, 'note' => $locked->not_awarded_note],
                actor: $actor, organizationId: $locked->organization_id);

            event(new CompetitionClosedWithoutAward($locked, $actor));

            return $locked;
        });
    }
}
