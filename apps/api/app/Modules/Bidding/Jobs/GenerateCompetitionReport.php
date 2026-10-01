<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Jobs;

use App\Modules\Bidding\Services\LiveStateManager;
use App\Modules\Bidding\Services\ReportGenerator;
use App\Modules\Competitions\Models\Competition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Renders the result report of one competition in one locale (ARCHITECTURE §7.14), on queue
 * `pdf`. Unique per (competition, locale) while queued, so repeated requests do not pile up.
 *
 * A rendering failure marks the report `failed` and never fails the lifecycle action that
 * triggered it (close, award, revoke); the next `GET …/report` queues it again.
 */
final class GenerateCompetitionReport implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $competitionId,
        public readonly string $locale,
    ) {
        $this->onQueue('pdf');
    }

    public function uniqueId(): string
    {
        return $this->competitionId.':'.$this->locale;
    }

    public function handle(ReportGenerator $reports, LiveStateManager $liveStates): void
    {
        $competition = Competition::query()->find($this->competitionId);

        if ($competition === null) {
            return;
        }

        try {
            $reports->generate($competition, $this->locale);
        } catch (Throwable $exception) {
            $reports->markFailed($competition, $this->locale, $liveStates->read($competition)->version);
            report($exception);
        }
    }
}
