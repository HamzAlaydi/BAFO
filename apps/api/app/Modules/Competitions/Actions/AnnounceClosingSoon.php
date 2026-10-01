<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Events\CompetitionClosingSoon;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\CompetitionSettings;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Closing-soon announcements (ARCHITECTURE §12 tick step 3): each threshold of the setting
 * `bidding.closing_soon_minutes` is announced once, and recorded in `notified_thresholds`.
 *
 * CONTRACT-GAP: when several thresholds are reached at once (a short competition, or a tick
 * after a pause), only the smallest one is announced; all of them are recorded.
 */
final readonly class AnnounceClosingSoon
{
    public function __construct(private CompetitionSettings $settings) {}

    /**
     * @return int|null the announced threshold in minutes
     */
    public function handle(int $competitionId): ?int
    {
        return DB::transaction(function () use ($competitionId): ?int {
            $competition = Competition::query()->whereKey($competitionId)->lockForUpdate()->first();
            $now = Date::now();

            if ($competition === null
                || $competition->status !== CompetitionStatus::Live
                || $competition->effective_close_at === null
                || $competition->effective_close_at->lessThanOrEqualTo($now)) {
                return null;
            }

            $secondsLeft = $competition->effective_close_at->getTimestamp() - $now->getTimestamp();
            $notified = array_map(intval(...), $competition->notified_thresholds);
            $reached = array_values(array_filter(
                $this->settings->closingSoonMinutes(),
                static fn (int $minutes): bool => $secondsLeft <= $minutes * 60 && ! in_array($minutes, $notified, true),
            ));

            if ($reached === []) {
                return null;
            }

            $competition->notified_thresholds = array_values(array_unique([...$notified, ...$reached]));
            $competition->save();

            $minutes = min($reached);

            event(new CompetitionClosingSoon($competition, $minutes));

            return $minutes;
        });
    }
}
