<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Console\Commands;

use App\Modules\Integrations\Models\ExportJob;
use App\Modules\Integrations\Models\ImportJob;
use App\Modules\Integrations\Models\WebhookEvent;
use App\Support\Files\File;
use App\Support\Files\FileStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * `integrations:prune` (daily, ARCHITECTURE §12):
 *
 *   - webhook events older than 30 days, with their deliveries (cascade);
 *   - import and export jobs finished more than 7 days ago, with their files (the source, the
 *     errors file, the export).
 *
 * CONTRACT-GAP: §12 says "expired import and export files"; `import_jobs.source_file_id` is a NOT
 * NULL restrict FK, so the jobs are deleted with their files.
 */
final class PruneIntegrationsCommand extends Command
{
    protected $signature = 'integrations:prune';

    protected $description = 'Delete old webhook events and expired import and export files';

    public function handle(FileStorage $files): int
    {
        $now = Date::now();

        $events = WebhookEvent::query()
            ->where('created_at', '<', $now->subDays((int) config('bafo.integrations.webhooks.retention_days')))
            ->delete();

        $cutoff = $now->subDays((int) config('bafo.integrations.files_retention_days'));
        $jobs = 0;

        ImportJob::query()->whereNotNull('finished_at')->where('finished_at', '<', $cutoff)->orderBy('id')
            ->each(static function (ImportJob $job) use ($files, &$jobs): void {
                DB::transaction(static function () use ($job, $files): void {
                    $fileIds = array_filter([$job->source_file_id, $job->errors_file_id]);
                    $job->delete();
                    File::query()->whereKey($fileIds)->get()->each(static fn (File $file) => $files->delete($file));
                });
                $jobs++;
            });

        ExportJob::query()->whereNotNull('finished_at')->where('finished_at', '<', $cutoff)->orderBy('id')
            ->each(static function (ExportJob $job) use ($files, &$jobs): void {
                DB::transaction(static function () use ($job, $files): void {
                    $fileId = $job->file_id;
                    $job->delete();

                    if ($fileId !== null) {
                        File::query()->whereKey($fileId)->get()->each(static fn (File $file) => $files->delete($file));
                    }
                });
                $jobs++;
            });

        $this->components->info("Deleted {$events} webhook events and {$jobs} import/export jobs with their files.");

        return self::SUCCESS;
    }
}
