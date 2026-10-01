<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Jobs;

use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Enums\JobStatus;
use App\Modules\Integrations\Events\ImportFinished;
use App\Modules\Integrations\Models\ImportJob;
use App\Modules\Integrations\Services\Imports\ImportFailed;
use App\Modules\Integrations\Services\Imports\VendorImportProcessor;
use App\Support\Auth\Actor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes a queued vendor import (ARCHITECTURE §14.7), queue `imports`. Idempotent: only a
 * `queued` job is picked up (`queued → processing → completed | failed`). Row messages are written
 * in the creator's language. Dispatches ImportFinished.
 */
final class ProcessVendorImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Below the Redis `retry_after` (150 s, config/queue.php), so a slow run is never picked up twice. */
    public int $timeout = 85;

    public function __construct(public readonly int $importJobId)
    {
        $this->onQueue('imports');
    }

    /**
     * The worker gave up (timeout or crash): the job must not stay `processing`.
     */
    public function failed(?Throwable $exception): void
    {
        $job = ImportJob::query()->find($this->importJobId);

        if ($job === null || in_array($job->status, [JobStatus::Completed, JobStatus::Failed], true)) {
            return;
        }

        $message = __('integrations.import.failures.unreadable');
        $job->forceFill([
            'status' => JobStatus::Failed,
            'failure_message' => is_string($message) ? $message : 'failed',
            'finished_at' => Date::now(),
        ])->save();

        event(new ImportFinished($job));
    }

    public function handle(VendorImportProcessor $processor): void
    {
        $job = DB::transaction(function (): ?ImportJob {
            $job = ImportJob::query()->lockForUpdate()->find($this->importJobId);

            if ($job === null || $job->status !== JobStatus::Queued) {
                return null;
            }

            $job->forceFill(['status' => JobStatus::Processing])->save();

            return $job;
        });

        if ($job === null) {
            return;
        }

        $creator = User::query()->with('membership')->find($job->created_by_user_id);
        $previousLocale = App::getLocale();
        App::setLocale($creator?->preferredLocale() ?? $previousLocale);

        try {
            $outcome = $processor->process($job, $creator !== null ? Actor::forUser($creator) : Actor::system());

            $job->forceFill([
                'status' => JobStatus::Completed,
                'total_rows' => $outcome->totalRows,
                'valid_rows' => $outcome->validRows,
                'created_rows' => $outcome->createdRows,
                'updated_rows' => $outcome->updatedRows,
                'error_rows' => $outcome->errorRows,
                'errors_preview' => $outcome->errorsPreview,
                'errors_file_id' => $outcome->errorsFile?->id,
                'finished_at' => Date::now(),
            ])->save();
        } catch (ImportFailed $failure) {
            $this->markFailed($job, $failure->localisedMessage());
        } catch (Throwable $e) {
            Log::error('integrations.import_failed', ['import_job_id' => $job->public_id, 'exception' => $e->getMessage()]);
            $message = __('integrations.import.failures.unreadable');
            $this->markFailed($job, is_string($message) ? $message : 'unreadable');
        } finally {
            App::setLocale($previousLocale);
        }

        event(new ImportFinished($job));
    }

    private function markFailed(ImportJob $job, string $message): void
    {
        $job->forceFill([
            'status' => JobStatus::Failed,
            'failure_message' => mb_substr($message, 0, 500),
            'finished_at' => Date::now(),
        ])->save();
    }
}
