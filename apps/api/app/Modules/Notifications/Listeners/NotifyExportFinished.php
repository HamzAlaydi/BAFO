<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Integrations\Models\ExportJob;
use App\Modules\Notifications\Services\IntegrationsNotifier;
use App\Modules\Notifications\Support\EventPayload;

/**
 * `export.finished` on `App\Modules\Integrations\Events\ExportFinished` (ARCHITECTURE §10, §11.3).
 */
final class NotifyExportFinished extends QueuedNotificationListener
{
    public function __construct(private readonly IntegrationsNotifier $notifier) {}

    /**
     * @param  object  $event  ExportFinished
     */
    public function handle(object $event): void
    {
        // CONTRACT-GAP: §10 gives the type (ExportJob) but not the property name.
        $this->notifier->exportFinished(EventPayload::find($event, ExportJob::class));
    }
}
