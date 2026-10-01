<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Listeners;

use App\Modules\Bidding\Events\AwardIssued;
use App\Modules\Bidding\Events\AwardRevoked;
use App\Modules\Bidding\Jobs\GenerateCompetitionReport;
use App\Modules\Bidding\Models\CompetitionReport;
use App\Modules\Competitions\Events\CompetitionClosed;
use App\Modules\Competitions\Events\CompetitionClosedWithoutAward;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Regenerates the result report (ARCHITECTURE §7.14): at close in the issuer creator's locale,
 * and after an award, a revoke or a close without award in every locale already rendered.
 */
final class QueueCompetitionReport implements ShouldHandleEventsAfterCommit, ShouldQueueAfterCommit
{
    public string $queue = 'pdf';

    public function handle(CompetitionClosed|CompetitionClosedWithoutAward|AwardIssued|AwardRevoked $event): void
    {
        $competition = Competition::query()->find($event->competition->id);

        if ($competition === null) {
            return;
        }

        /** @var list<string> $locales */
        $locales = CompetitionReport::query()->where('competition_id', $competition->id)->pluck('locale')->all();
        $locales[] = self::creatorLocale($competition);

        foreach (array_unique($locales) as $locale) {
            GenerateCompetitionReport::dispatch($competition->id, $locale);
        }
    }

    private static function creatorLocale(Competition $competition): string
    {
        $locale = $competition->created_by_user_id !== null
            ? User::query()->whereKey($competition->created_by_user_id)->value('locale')
            : null;

        return in_array($locale, ['ar', 'en'], true) ? $locale : 'ar';
    }
}
