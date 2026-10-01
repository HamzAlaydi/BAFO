<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Actions\RemoveDeletedAccountDevices;
use App\Modules\Notifications\Support\EventPayload;

/**
 * Removes the device tokens of a deleted account on `App\Modules\Identity\Events\AccountDeleted`
 * (ARCHITECTURE §10, §13.8).
 */
final class RemoveDeviceTokens extends QueuedNotificationListener
{
    public function __construct(private readonly RemoveDeletedAccountDevices $action) {}

    /**
     * @param  object  $event  AccountDeleted
     */
    public function handle(object $event): void
    {
        $this->action->handle(EventPayload::int($event, 'userId'));
    }
}
