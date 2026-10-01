<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\Exports;

use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\ExportFormat;
use App\Modules\Integrations\Enums\ExportType;
use App\Modules\Integrations\Enums\JobStatus;
use App\Modules\Integrations\Jobs\ProcessExport;
use App\Modules\Integrations\Models\ExportJob;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * `POST /integrations/exports` (ARCHITECTURE §14.8): a `queued` export job, processed
 * asynchronously (queue `imports`).
 */
final class StartExport
{
    /**
     * @param  array<string, string>  $filters  `{competition_id}` (public id) or `{from, to}`
     */
    public function handle(Organization $organization, ExportType $type, ExportFormat $format, array $filters, Actor $actor): ExportJob
    {
        return DB::transaction(static function () use ($organization, $type, $format, $filters, $actor): ExportJob {
            $job = ExportJob::query()->create([
                'organization_id' => $organization->id,
                'created_by_user_id' => $actor->userId,
                'type' => $type,
                'format' => $format,
                'filters' => $filters,
                'status' => JobStatus::Queued,
            ]);

            AuditLogger::log('export_job.created', null, meta: [
                'export_job_id' => $job->public_id,
                'type' => $type->value,
                'format' => $format->value,
                'filters' => $filters,
            ], actor: $actor, organizationId: $organization->id);

            ProcessExport::dispatch($job->id)->afterCommit();

            return $job;
        });
    }
}
