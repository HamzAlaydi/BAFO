<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Integrations\Models\ImportJob;
use App\Modules\Notifications\Services\IntegrationsNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `import.finished` on `App\Modules\Integrations\Events\ImportFinished` (ARCHITECTURE §10, §11.3).
 */
final class NotifyImportFinished extends QueuedNotificationListener
{
    public function __construct(private readonly IntegrationsNotifier $notifier) {}

    /**
     * @param  object  $event  ImportFinished
     */
    public function handle(object $event): void
    {
        // CONTRACT-GAP: §10 gives the type (ImportJob) but not the property name.
        $this->notifier->importFinished(EventPayload::find($event, ImportJob::class));
    }
}
