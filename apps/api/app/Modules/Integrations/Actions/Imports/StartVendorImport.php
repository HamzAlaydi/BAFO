<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\Imports;

use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\ImportMode;
use App\Modules\Integrations\Enums\ImportType;
use App\Modules\Integrations\Enums\JobStatus;
use App\Modules\Integrations\Jobs\ProcessVendorImport;
use App\Modules\Integrations\Models\ImportJob;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Files\FilePurpose;
use App\Support\Files\FileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * `POST /integrations/imports` (ARCHITECTURE §14.7): stores the source file (csv or xlsx,
 * ≤ 20 MB, checked by FileStorage), creates the `queued` job and queues ProcessVendorImport
 * (`imports`) after commit.
 */
final readonly class StartVendorImport
{
    public function __construct(private FileStorage $files) {}

    public function handle(Organization $organization, UploadedFile $upload, ImportType $type, ImportMode $mode, Actor $actor): ImportJob
    {
        $source = $this->files->store($upload, FilePurpose::ImportSource, $organization->id, $actor->userId);

        return DB::transaction(static function () use ($organization, $source, $type, $mode, $actor): ImportJob {
            $job = ImportJob::query()->create([
                'organization_id' => $organization->id,
                'created_by_user_id' => $actor->userId,
                'type' => $type,
                'mode' => $mode,
                'status' => JobStatus::Queued,
                'source_file_id' => $source->id,
            ]);

            // Jobs have no morph alias: the organization's feed records the job by id.
            AuditLogger::log('import_job.created', null, meta: [
                'import_job_id' => $job->public_id,
                'type' => $type->value,
                'mode' => $mode->value,
                'file' => $source->original_name,
            ], actor: $actor, organizationId: $organization->id);

            ProcessVendorImport::dispatch($job->id)->afterCommit();

            return $job;
        });
    }
}
