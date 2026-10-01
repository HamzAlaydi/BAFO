<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console\Commands;

use App\Modules\Identity\Actions\PruneIdentityData;
use Illuminate\Console\Command;

/**
 * `identity:prune` (ARCHITECTURE §12, daily).
 */
final class PruneIdentityCommand extends Command
{
    protected $signature = 'identity:prune';

    protected $description = 'Delete OTP codes older than 24 hours and spend expired team invitation tokens';

    public function handle(PruneIdentityData $prune): int
    {
        $result = $prune->handle();

        $this->info("Deleted {$result['otp_codes']} OTP codes; expired {$result['invite_tokens']} invitation tokens.");

        return self::SUCCESS;
    }
}
