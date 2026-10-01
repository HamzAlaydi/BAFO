<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Competitions\Contracts\CompetitionStateMachine;
use App\Modules\Competitions\Enums\CompetitionStatus as S;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Events\CompetitionStatusChanged;
use App\Modules\Competitions\Models\Competition;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\Date;

/**
 * The competition state machine of ARCHITECTURE §6.1 (T1–T12).
 */
final class StateMachine implements CompetitionStateMachine
{
    /**
     * Allowed transitions: from => list of targets.
     *
     * @var array<string, list<S>>
     */
    private const array TRANSITIONS = [
        'draft' => [S::Scheduled, S::Live],                      // T1, T2
        'scheduled' => [S::Live, S::Cancelled],                  // T3, T4
        'live' => [S::Closed, S::Cancelled],                     // T5, T6
        'closed' => [S::BafoRound, S::Awarded, S::NotAwarded],   // T7, T10, T12
        'bafo_round' => [S::Closed, S::Cancelled],               // T8, T9
        'awarded' => [S::Closed],                                // T11
        'not_awarded' => [],
        'cancelled' => [],
    ];

    public function allows(S $from, S $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value], true);
    }

    public function transition(Competition $locked, S $to, Actor $actor, array $attributes = []): void
    {
        $from = $locked->status;

        if (! $this->allows($from, $to)) {
            throw self::invalid($from, $to);
        }

        $now = Date::now();
        $stamp = match ($to) {
            S::Scheduled => ['published_at' => $now],
            S::Live => $from === S::Draft ? ['published_at' => $now, 'opened_at' => $now] : ['opened_at' => $now],
            // T8 (end of the BAFO round) and T11 (revoke) keep the first close time.
            S::Closed => $from === S::Live ? ['closed_at' => $now] : [],
            S::Awarded => ['awarded_at' => $now],
            S::NotAwarded => ['not_awarded_at' => $now],
            S::Cancelled => ['cancelled_at' => $now],
            default => [],
        };

        if ($from === S::Awarded) {
            $stamp['awarded_at'] = null;
        }

        $locked->forceFill([...$stamp, ...$attributes]);
        $locked->status = $to;
        $locked->save();

        event(new CompetitionStatusChanged($locked, $from, $to, $actor));
    }

    /**
     * 409 for an invitation outside the §6.2 table.
     */
    public static function invalidInvitation(InvitationStatus $from, InvitationStatus $to): ApiException
    {
        return new ApiException(
            errorCode: 'invalid_state_transition',
            status: 409,
            details: ['from' => $from->value, 'to' => $to->value],
        );
    }

    public static function invalid(S $from, ?S $to = null): ApiException
    {
        return new ApiException(
            errorCode: 'invalid_state_transition',
            status: 409,
            details: $to === null ? ['status' => $from->value] : ['from' => $from->value, 'to' => $to->value],
        );
    }
}
