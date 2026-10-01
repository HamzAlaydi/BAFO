<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Console\Commands;

use App\Modules\Competitions\Actions\AnnounceClosingSoon;
use App\Modules\Competitions\Actions\ExpireInvitationsAtCutoff;
use App\Modules\Competitions\Actions\OpenCompetition;
use App\Modules\Competitions\Actions\StartFinalWindow;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Jobs\CloseCompetition;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\CompetitionSettings;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The lifecycle scheduler (ARCHITECTURE §12), every 10 seconds:
 *
 *   1. scheduled → live where `bidding_opens_at ≤ now`
 *   2. live with a due final window and no `final_window_started_at` → stamp + event
 *   3. closing-soon thresholds → CompetitionClosingSoon
 *   4. CloseCompetition for live rows with `effective_close_at ≤ now` (safety net for the job)
 *   5. sent / viewed invitations of competitions past `invitation_cutoff_at` → expired
 *
 * Every step is idempotent and runs one short transaction per competition.
 */
final class CompetitionsTickCommand extends Command
{
    protected $signature = 'competitions:tick';

    protected $description = 'Open, announce, close and expire competitions whose time has come';

    public function handle(
        OpenCompetition $open,
        StartFinalWindow $finalWindow,
        AnnounceClosingSoon $closingSoon,
        ExpireInvitationsAtCutoff $expire,
        CompetitionSettings $settings,
    ): int {
        $now = Date::now();
        $counts = ['opened' => 0, 'final_windows' => 0, 'closing_soon' => 0, 'close_dispatched' => 0, 'expired' => 0];

        foreach ($this->ids(Competition::query()
            ->where('status', CompetitionStatus::Scheduled->value)
            ->where('bidding_opens_at', '<=', $now)) as $id) {
            $counts['opened'] += $this->attempt(static fn (): bool => $open->handle($id), $id) === true ? 1 : 0;
        }

        foreach ($this->ids(Competition::query()
            ->where('status', CompetitionStatus::Live->value)
            ->whereNull('final_window_started_at')
            ->where('final_window_starts_at', '<=', $now)) as $id) {
            $counts['final_windows'] += $this->attempt(static fn (): bool => $finalWindow->handle($id), $id) === true ? 1 : 0;
        }

        $largestThreshold = max([0, ...$settings->closingSoonMinutes()]);

        if ($largestThreshold > 0) {
            foreach ($this->ids(Competition::query()
                ->where('status', CompetitionStatus::Live->value)
                ->where('effective_close_at', '>', $now)
                ->where('effective_close_at', '<=', $now->addMinutes($largestThreshold))) as $id) {
                $counts['closing_soon'] += $this->attempt(static fn (): ?int => $closingSoon->handle($id), $id) !== null ? 1 : 0;
            }
        }

        foreach ($this->ids(Competition::query()
            ->where('status', CompetitionStatus::Live->value)
            ->where('effective_close_at', '<=', $now)) as $id) {
            CloseCompetition::dispatch($id);
            $counts['close_dispatched']++;
        }

        foreach ($this->ids(Competition::query()
            ->whereIn('status', [CompetitionStatus::Scheduled->value, CompetitionStatus::Live->value])
            ->where('invitation_cutoff_at', '<=', $now)
            ->whereHas('invitations', static fn ($query) => $query->whereIn('status', [
                InvitationStatus::Sent->value, InvitationStatus::Viewed->value,
            ]))) as $id) {
            $counts['expired'] += (int) $this->attempt(static fn (): int => $expire->handle($id), $id);
        }

        if (array_sum($counts) > 0) {
            Log::info('competitions:tick', $counts);
        }

        $this->line(collect($counts)->map(static fn (int $n, string $k): string => "{$k}={$n}")->implode(' '));

        return self::SUCCESS;
    }

    /**
     * @param  Builder<Competition>  $query
     * @return list<int>
     */
    private function ids($query): array
    {
        /** @var list<int> $ids */
        $ids = $query->orderBy('id')->limit(500)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        return $ids;
    }

    /**
     * One competition failing must not stop the tick for the others.
     *
     * @template T
     *
     * @param  callable(): T  $step
     * @return T|null
     */
    private function attempt(callable $step, int $competitionId): mixed
    {
        try {
            return $step();
        } catch (Throwable $e) {
            Log::error('competitions:tick step failed', ['competition_id' => $competitionId, 'exception' => $e->getMessage()]);
            report($e);

            return null;
        }
    }
}
