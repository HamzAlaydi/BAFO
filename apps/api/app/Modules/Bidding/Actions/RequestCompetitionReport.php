<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Actions;

use App\Modules\Bidding\Enums\ReportStatus;
use App\Modules\Bidding\Jobs\GenerateCompetitionReport;
use App\Modules\Bidding\Models\CompetitionReport;
use App\Modules\Bidding\Services\LiveStateManager;
use App\Modules\Competitions\Models\Competition;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * `GET …/report?locale=` (API.md §1.6, ARCHITECTURE §7.14): the current report, or a queued
 * generation when none exists for the locale or the stored one is older than the live state.
 * Before the first close → 409 `report_not_available`.
 */
final readonly class RequestCompetitionReport
{
    public function __construct(private LiveStateManager $liveStates) {}

    public function handle(Competition $competition, string $locale): CompetitionReport
    {
        if ($competition->closed_at === null) {
            throw new ApiException('report_not_available', 'bidding.errors.report_not_available', 409);
        }

        $version = $this->liveStates->read($competition)->version;

        $report = CompetitionReport::query()
            ->where('competition_id', $competition->id)
            ->where('locale', $locale)
            ->first();

        if ($report !== null && self::isCurrent($report, $version)) {
            return $report;
        }

        $report = DB::transaction(static function () use ($competition, $locale, $version): CompetitionReport {
            $report = CompetitionReport::query()
                ->where('competition_id', $competition->id)
                ->where('locale', $locale)
                ->lockForUpdate()
                ->first() ?? new CompetitionReport(['competition_id' => $competition->id, 'locale' => $locale]);

            $report->fill(['status' => ReportStatus::Pending, 'live_version' => $version])->save();

            return $report;
        });

        GenerateCompetitionReport::dispatch($competition->id, $locale)->afterCommit();

        return $report->refresh();
    }

    public static function isCurrent(CompetitionReport $report, int $version): bool
    {
        return $report->status === ReportStatus::Ready && $report->file_id !== null && $report->live_version >= $version;
    }
}
