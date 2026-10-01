<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Events\InvitationsExpired;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use Carbon\CarbonImmutable;

/**
 * sent / viewed → expired (ARCHITECTURE §6.2): at the invitation cut-off (tick), at the close
 * (§7.8) and at the cancellation (T4, T6, T9). Runs inside the caller's transaction.
 */
final class InvitationExpirer
{
    /**
     * @return list<int> the expired invitation ids
     */
    public function expire(Competition $competition, CarbonImmutable $at): array
    {
        /** @var list<int> $ids */
        $ids = Invitation::query()
            ->where('competition_id', $competition->id)
            ->whereIn('status', [InvitationStatus::Sent->value, InvitationStatus::Viewed->value])
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return [];
        }

        Invitation::query()->whereKey($ids)->update([
            'status' => InvitationStatus::Expired->value,
            'expired_at' => $at,
            'updated_at' => $at,
        ]);

        event(new InvitationsExpired($competition, $ids));

        return $ids;
    }
}
