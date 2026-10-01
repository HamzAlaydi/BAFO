<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Participant presence on the live screen (ARCHITECTURE §9.5): `hb:{competitionId}:{participantId}`
 * lives 45 s after each heartbeat. The issuer snapshot counts the live keys; Notifications uses
 * `isOnline()` to suppress pushes to participants who are watching.
 */
final class Heartbeats
{
    public static function key(int $competitionId, int $participantId): string
    {
        return "hb:{$competitionId}:{$participantId}";
    }

    /**
     * Records a heartbeat. At most one refresh per user per competition per interval; excess
     * calls are ignored (the endpoint still answers 204).
     */
    public function beat(int $competitionId, int $participantId, int $userId): void
    {
        $interval = (int) config('bafo.bidding.heartbeat.min_interval_seconds', 10);

        if (! Cache::add("hb-rate:{$competitionId}:{$userId}", 1, $interval)) {
            return;
        }

        Cache::put(self::key($competitionId, $participantId), 1, (int) config('bafo.bidding.heartbeat.ttl_seconds', 45));
    }

    public function isOnline(int $competitionId, int $participantId): bool
    {
        return Cache::has(self::key($competitionId, $participantId));
    }

    /**
     * @param  iterable<int>  $participantIds
     */
    public function onlineCount(int $competitionId, iterable $participantIds): int
    {
        $keys = [];

        foreach ($participantIds as $participantId) {
            $keys[] = self::key($competitionId, $participantId);
        }

        if ($keys === []) {
            return 0;
        }

        return count(array_filter(Cache::many($keys), static fn (mixed $value): bool => $value !== null));
    }
}
