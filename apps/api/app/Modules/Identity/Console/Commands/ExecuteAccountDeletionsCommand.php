<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console\Commands;

use App\Modules\Identity\Actions\ExecuteAccountDeletion;
use App\Modules\Identity\Enums\DeletionStatus;
use App\Modules\Identity\Models\AccountDeletionRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * `identity:execute-account-deletions` (ARCHITECTURE §12, hourly): runs every pending request whose
 * `scheduled_for` has passed, one transaction per request (§13.8).
 */
final class ExecuteAccountDeletionsCommand extends Command
{
    protected $signature = 'identity:execute-account-deletions';

    protected $description = 'Execute the account deletion requests that are due';

    public function handle(ExecuteAccountDeletion $execute): int
    {
        $executed = 0;
        $failed = 0;

        AccountDeletionRequest::query()
            ->where('status', DeletionStatus::Pending->value)
            ->where('scheduled_for', '<=', Date::now())
            ->orderBy('id')
            ->each(function (AccountDeletionRequest $request) use ($execute, &$executed, &$failed): void {
                try {
                    if ($execute->handle($request)) {
                        $executed++;
                    }
                } catch (Throwable $e) {
                    $failed++;
                    Log::error('Account deletion failed.', ['account_deletion_request_id' => $request->id, 'exception' => $e->getMessage()]);
                    report($e);
                }
            });

        $this->info("Executed {$executed} account deletion requests; {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
