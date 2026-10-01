<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Jobs;

use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Enums\ExportFormat;
use App\Modules\Integrations\Enums\JobStatus;
use App\Modules\Integrations\Events\ExportFinished;
use App\Modules\Integrations\Models\ExportJob;
use App\Modules\Integrations\Services\Exports\ExportBuilder;
use App\Modules\Integrations\Services\Spreadsheets\SheetData;
use App\Modules\Integrations\Services\Spreadsheets\SpreadsheetWriter;
use App\Support\Files\FilePurpose;
use App\Support\Files\FileStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds a queued export (ARCHITECTURE §14.8), queue `imports`: rows from ExportBuilder, written
 * as CSV (UTF-8 with BOM) or XLSX, stored as a `FilePurpose::Export` file. Idempotent: only a
 * `queued` job is picked up. Dispatches ExportFinished.
 */
final class ProcessExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Below the Redis `retry_after` (150 s, config/queue.php), so a slow run is never picked up twice. */
    public int $timeout = 85;

    public function __construct(public readonly int $exportJobId)
    {
        $this->onQueue('imports');
    }

    /**
     * The worker gave up (timeout or crash): the job must not stay `processing`.
     */
    public function failed(?Throwable $exception): void
    {
        $job = ExportJob::query()->find($this->exportJobId);

        if ($job === null || in_array($job->status, [JobStatus::Completed, JobStatus::Failed], true)) {
            return;
        }

        $message = __('integrations.exports.failed');
        $job->forceFill([
            'status' => JobStatus::Failed,
            'failure_message' => is_string($message) ? $message : 'failed',
            'finished_at' => Date::now(),
        ])->save();

        event(new ExportFinished($job));
    }

    public function handle(ExportBuilder $builder, SpreadsheetWriter $writer, FileStorage $files): void
    {
        $job = DB::transaction(function (): ?ExportJob {
            $job = ExportJob::query()->lockForUpdate()->find($this->exportJobId);

            if ($job === null || $job->status !== JobStatus::Queued) {
                return null;
            }

            $job->forceFill(['status' => JobStatus::Processing])->save();

            return $job;
        });

        if ($job === null) {
            return;
        }

        $previousLocale = App::getLocale();
        $locale = User::query()->whereKey($job->created_by_user_id)->value('locale');
        App::setLocale(is_string($locale) ? $locale : $previousLocale);

        try {
            $table = $builder->build($job);
            $bytes = $writer->write($job->format, [new SheetData($job->type->value, $table->lines())]);

            $file = $files->storeContents(
                $bytes,
                $table->name.'-'.Date::now()->format('Ymd-His').'.'.$job->format->value,
                $job->format === ExportFormat::Xlsx ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv',
                FilePurpose::Export,
                $job->organization_id,
            );

            $job->forceFill([
                'status' => JobStatus::Completed,
                'file_id' => $file->id,
                'row_count' => count($table->rows),
                'finished_at' => Date::now(),
            ])->save();
        } catch (Throwable $e) {
            Log::error('integrations.export_failed', ['export_job_id' => $job->public_id, 'exception' => $e->getMessage()]);
            $message = __('integrations.exports.failed');

            $job->forceFill([
                'status' => JobStatus::Failed,
                'failure_message' => is_string($message) ? $message : 'failed',
                'finished_at' => Date::now(),
            ])->save();
        } finally {
            App::setLocale($previousLocale);
        }

        event(new ExportFinished($job));
    }
}
