<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Data\Delivery;
use App\Modules\Notifications\Data\NotificationPayload;
use App\Modules\Notifications\Enums\NotificationType;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Sends a catalogue notification to its recipients (ARCHITECTURE §11.1).
 *
 * - One notification instance per recipient, so every row gets its own id.
 * - A user listed twice gets the first delivery only.
 * - A delivery whose channels are all excluded is skipped.
 *
 * Each notification is queued on `notifications`, one job per channel (Laravel).
 */
final readonly class NotificationDispatcher
{
    public function __construct(private Cache $cache) {}

    /**
     * @param  iterable<Delivery>  $deliveries
     * @return int the number of users notified
     */
    public function send(NotificationType $type, NotificationPayload $payload, iterable $deliveries): int
    {
        $class = $type->notificationClass();
        $seen = [];
        $sent = 0;

        foreach ($deliveries as $delivery) {
            $userId = $delivery->user->getKey();

            if (isset($seen[$userId])) {
                continue;
            }

            $seen[$userId] = true;
            $notification = $class::fromPayload($payload, $delivery->without);

            if ($notification->channels() !== []) {
                $delivery->user->notify($notification);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * A catalogue throttle (§11.3): true the first time a key is seen within the window.
     * `Cache::add` is atomic on Redis, so concurrent workers let one notification through.
     */
    public function allow(string $key, int $seconds): bool
    {
        return $this->cache->add('notifications:throttle:'.$key, 1, $seconds);
    }
}
