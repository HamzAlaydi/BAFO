<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Reads the live-screen heartbeats that Bidding stores (ARCHITECTURE §9.5), to suppress pushes
 * for participants who are watching the live screen.
 *
 * CONTRACT-GAP: §9.5 names the key `hb:{competitionId}:{participantId}` without saying which ids;
 * the internal (bigint) ids are assumed. Bidding confirms in its handoff.
 */
final readonly class LiveHeartbeats
{
    public function __construct(private Cache $cache) {}

    public static function key(int $competitionId, int $participantId): string
    {
        return "hb:{$competitionId}:{$participantId}";
    }

    public function isWatching(int $competitionId, int $participantId): bool
    {
        return $this->cache->has(self::key($competitionId, $participantId));
    }
}
