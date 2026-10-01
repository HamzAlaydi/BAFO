<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\Organization;

/**
 * What keeps an organization from being deleted (ARCHITECTURE §13.8): competitions it issues in
 * `scheduled`, `live`, `bafo_round` or `closed`, and participations in competitions in
 * `scheduled`, `live` or `bafo_round`.
 */
final class DeletionBlockers
{
    /**
     * @return list<array{type: string, competition_id: string, title: string}>
     */
    public function for(Organization $organization): array
    {
        $issued = Competition::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [
                CompetitionStatus::Scheduled->value,
                CompetitionStatus::Live->value,
                CompetitionStatus::BafoRound->value,
                CompetitionStatus::Closed->value,
            ])
            ->orderBy('id')
            ->get(['id', 'public_id', 'title']);

        $participating = Competition::query()
            ->whereIn('id', Participant::query()->select('competition_id')->where('organization_id', $organization->id))
            ->whereIn('status', [
                CompetitionStatus::Scheduled->value,
                CompetitionStatus::Live->value,
                CompetitionStatus::BafoRound->value,
            ])
            ->orderBy('id')
            ->get(['id', 'public_id', 'title']);

        $blockers = [];

        foreach ($issued as $competition) {
            $blockers[] = ['type' => 'issued_competition', 'competition_id' => $competition->public_id, 'title' => $competition->title];
        }

        foreach ($participating as $competition) {
            $blockers[] = ['type' => 'participation', 'competition_id' => $competition->public_id, 'title' => $competition->title];
        }

        return $blockers;
    }
}
