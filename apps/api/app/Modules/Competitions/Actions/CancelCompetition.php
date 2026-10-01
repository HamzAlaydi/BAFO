<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Contracts\CompetitionStateMachine;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Events\CompetitionCancelled;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\CloseReasonGuard;
use App\Modules\Competitions\Services\InvitationExpirer;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * T4 / T6 / T9: scheduled, live or bafo_round → cancelled (ARCHITECTURE §6.1), by the issuer,
 * an API client with `competitions:manage`, or an admin. There is no early close: once bidding
 * opened, the issuer can only cancel. Pending invitations expire.
 */
final readonly class CancelCompetition
{
    public function __construct(
        private CompetitionStateMachine $stateMachine,
        private InvitationExpirer $invitations,
    ) {}

    public function handle(Competition $competition, CloseReason $reason, ?string $note, Actor $actor): Competition
    {
        CloseReasonGuard::assert($reason, CloseReasonKind::Cancel, $note);

        return DB::transaction(function () use ($competition, $reason, $note, $actor): Competition {
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->transition($locked, CompetitionStatus::Cancelled, $actor, [
                'cancel_reason_id' => $reason->id,
                'cancel_note' => CloseReasonGuard::cleanNote($note),
                'cancelled_by_user_id' => $actor->userId,
                'cancelled_by_admin_id' => $actor->adminId,
            ]);

            $this->invitations->expire($locked, Date::now());

            AuditLogger::log('competition.cancelled', $locked, meta: ['reason' => $reason->code, 'note' => $locked->cancel_note],
                actor: $actor, organizationId: $locked->organization_id);

            event(new CompetitionCancelled($locked, $actor));

            return $locked;
        });
    }
}
