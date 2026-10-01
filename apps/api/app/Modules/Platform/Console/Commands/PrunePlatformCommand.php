<?php

declare(strict_types=1);

namespace App\Modules\Platform\Console\Commands;

use App\Support\Idempotency\IdempotencyKey;
use Illuminate\Console\Command;

/**
 * `platform:prune` (daily, ARCHITECTURE §12): deletes expired idempotency keys.
 */
final class PrunePlatformCommand extends Command
{
    protected $signature = 'platform:prune';

    protected $description = 'Delete expired idempotency keys';

    public function handle(): int
    {
        $deleted = IdempotencyKey::query()->expired()->delete();

        $this->components->info("Deleted {$deleted} expired idempotency keys.");

        return self::SUCCESS;
    }
}
