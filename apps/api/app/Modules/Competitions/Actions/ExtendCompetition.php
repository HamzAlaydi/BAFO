<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Contracts\CompetitionTimingService;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\ExtensionKind;
use App\Modules\Competitions\Enums\Phase;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\CompetitionSettings;
use App\Modules\Competitions\Services\StateMachine;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Clock\DbClock;
use App\Support\Exceptions\ApiException;
use App\Support\Http\Iso;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Manual extension by the issuer, or admin extension (ARCHITECTURE §7.17). Only while live.
 * The new close must be at least `competitions.min_extend_minutes` after the current close and
 * within `competitions.max_duration_days` of the opening (422 `extend_invalid`).
 */
final readonly class ExtendCompetition
{
    public function __construct(
        private CompetitionTimingService $timing,
        private CompetitionSettings $settings,
        private DbClock $clock,
    ) {}

    public function handle(Competition $competition, CarbonImmutable $newCloseAt, string $reason, Actor $actor): Competition
    {
        return DB::transaction(function () use ($competition, $newCloseAt, $reason, $actor): Competition {
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();
            $now = $this->clock->now();

            if ($locked->status !== CompetitionStatus::Live || $locked->effective_close_at === null) {
                throw StateMachine::invalid($locked->status);
            }

            $currentClose = $locked->effective_close_at;
            $minNewClose = $currentClose->addMinutes($this->settings->minExtendMinutes());
            $opens = $locked->bidding_opens_at ?? $locked->opened_at ?? $now;
            $maxNewClose = $opens->addDays($this->settings->maxDurationDays());

            if ($newCloseAt->lessThan($minNewClose) || $newCloseAt->greaterThan($maxNewClose)) {
                $message = __('competitions.errors.extend_invalid', ['min' => Iso::format($minNewClose)]);

                throw new ApiException(
                    errorCode: 'extend_invalid',
                    messageKey: 'competitions.errors.extend_invalid',
                    status: 422,
                    errors: ['new_close_at' => [is_string($message) ? $message : 'extend_invalid']],
                    replace: ['min' => (string) Iso::format($minNewClose)],
                    details: ['min_new_close_at' => Iso::format($minNewClose), 'max_new_close_at' => Iso::format($maxNewClose)],
                );
            }

            // delta = new_close_at − effective_close_at (§7.17)
            $delta = $currentClose->diff($newCloseAt);
            $shift = static fn (CarbonImmutable $time): CarbonImmutable => $time->add($delta);

            if ($locked->hard_stop_at !== null) {
                $locked->hard_stop_at = $shift($locked->hard_stop_at);
            }

            if ($locked->phaseAt($now) === Phase::Initial && $locked->final_window_starts_at !== null) {
                $locked->final_window_starts_at = $shift($locked->final_window_starts_at);
            }

            if ($locked->invitation_cutoff_at !== null && $now->lessThan($locked->invitation_cutoff_at)) {
                $locked->invitation_cutoff_at = $shift($locked->invitation_cutoff_at);
            }

            $kind = $actor->isAdmin() ? ExtensionKind::Admin : ExtensionKind::Manual;
            $extension = $this->timing->extend($locked, $newCloseAt, $kind, $actor, reason: $reason);

            AuditLogger::log('competition.extended', $locked, [
                'effective_close_at' => ['from' => Iso::format($extension->previous_close_at), 'to' => Iso::format($newCloseAt)],
            ], ['kind' => $kind->value, 'reason' => $reason], $actor, $locked->organization_id);

            return $locked;
        });
    }
}
