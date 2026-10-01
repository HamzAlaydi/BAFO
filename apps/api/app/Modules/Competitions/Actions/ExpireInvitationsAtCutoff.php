<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\InvitationExpirer;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Pending invitations (sent, viewed) expire at `invitation_cutoff_at` (ARCHITECTURE §6.2,
 * §12 tick step 5).
 */
final readonly class ExpireInvitationsAtCutoff
{
    public function __construct(private InvitationExpirer $expirer) {}

    /**
     * @return int the number of expired invitations
     */
    public function handle(int $competitionId): int
    {
        return DB::transaction(function () use ($competitionId): int {
            $competition = Competition::query()->whereKey($competitionId)->lockForUpdate()->first();
            $now = Date::now();

            if ($competition === null
                || ! in_array($competition->status, [CompetitionStatus::Scheduled, CompetitionStatus::Live], true)
                || $competition->invitation_cutoff_at === null
                || $competition->invitation_cutoff_at->greaterThan($now)) {
                return 0;
            }

            return count($this->expirer->expire($competition, $now));
        });
    }
}
