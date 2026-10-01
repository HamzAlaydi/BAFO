<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Base of the Notifications listeners of domain events (ARCHITECTURE §4.10, §10): queued on
 * `notifications`, run after the producing transaction commits, 3 tries.
 *
 * Listeners are bound by event class-string in `NotificationsServiceProvider::EVENT_LISTENERS`,
 * so they work as the producing modules land. `handle()` takes the event as `object` and reads
 * the §10 properties through `EventPayload`, which enforces their types at runtime; the
 * notifiers behind the listeners are fully typed.
 */
abstract class QueuedNotificationListener implements ShouldHandleEventsAfterCommit, ShouldQueueAfterCommit
{
    public string $queue = 'notifications';

    public int $tries = 3;

    abstract public function handle(object $event): void;
}
