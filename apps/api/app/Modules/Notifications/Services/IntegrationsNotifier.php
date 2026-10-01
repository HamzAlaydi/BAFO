<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Integrations\Models\ExportJob;
use App\Modules\Integrations\Models\ImportJob;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Notifications\Data\Delivery;
use App\Modules\Notifications\Data\NotificationPayload;
use App\Modules\Notifications\Data\NotificationSubject;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\NotificationRoutes;

/**
 * Integrations notifications (ARCHITECTURE §11.3, rows `webhook.endpoint_disabled`,
 * `import.finished`, `export.finished`). "Integration users" hold `integrations.manage`.
 */
final readonly class IntegrationsNotifier
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private Recipients $recipients,
    ) {}

    /** `webhook.endpoint_disabled`: integration users, with the endpoint URL. */
    public function webhookEndpointDisabled(WebhookEndpoint $endpoint): int
    {
        return $this->dispatcher->send(
            NotificationType::WebhookEndpointDisabled,
            new NotificationPayload(['url' => $endpoint->url], NotificationSubject::of($endpoint), NotificationRoutes::INTEGRATIONS),
            Delivery::toEach($this->recipients->membersWith($endpoint->organization_id, Permission::IntegrationsManage)),
        );
    }

    /** `import.finished` (in-app): the user who started the import, with the row counts. */
    public function importFinished(ImportJob $job): int
    {
        $creator = $this->recipients->user($job->created_by_user_id);

        return $this->dispatcher->send(
            NotificationType::ImportFinished,
            new NotificationPayload(
                [
                    'mode' => $job->mode->value,
                    'status' => $job->status->value,
                    'created' => $job->created_rows,
                    'updated' => $job->updated_rows,
                    'errors' => $job->error_rows,
                ],
                // CONTRACT-GAP: import and export jobs have no morph alias (§4.9); the subject uses
                // the snake names `import_job` / `export_job`.
                new NotificationSubject('import_job', $job->public_id),
                NotificationRoutes::INTEGRATIONS,
            ),
            $creator === null ? [] : [new Delivery($creator)],
        );
    }

    /** `export.finished` (in-app): the user who asked for the export. */
    public function exportFinished(ExportJob $job): int
    {
        $creator = $this->recipients->user($job->created_by_user_id);

        return $this->dispatcher->send(
            NotificationType::ExportFinished,
            new NotificationPayload(
                ['export_type' => $job->type->value, 'format' => $job->format->value, 'status' => $job->status->value],
                new NotificationSubject('export_job', $job->public_id),
                NotificationRoutes::INTEGRATIONS,
            ),
            $creator === null ? [] : [new Delivery($creator)],
        );
    }
}
