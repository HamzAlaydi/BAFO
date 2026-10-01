<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\DeviceToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * `notifications:prune-devices` (ARCHITECTURE §12, weekly): removes device tokens not seen for
 * 90 days (`bafo.notifications.devices.stale_after_days`) and those of deleted users.
 */
final class PruneStaleDevices
{
    /**
     * @return int the number of device tokens removed
     */
    public function handle(CarbonImmutable $now, int $staleAfterDays = 90): int
    {
        return DB::transaction(static fn (): int => DeviceToken::query()
            ->where('last_seen_at', '<', $now->subDays($staleAfterDays))
            ->orWhereIn('user_id', User::onlyTrashed()->select('id'))
            ->delete());
    }
}
