<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Console\Commands;

use App\Modules\Notifications\Actions\PruneStaleDevices;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * `notifications:prune-devices` (ARCHITECTURE §12, weekly).
 */
final class PruneDevicesCommand extends Command
{
    protected $signature = 'notifications:prune-devices';

    protected $description = 'Remove push device tokens not seen for 90 days, and those of deleted users';

    public function handle(PruneStaleDevices $action): int
    {
        $days = config('bafo.notifications.devices.stale_after_days', 90);
        $removed = $action->handle(CarbonImmutable::now(), is_int($days) ? $days : 90);

        $this->info("Removed {$removed} device token(s).");

        return self::SUCCESS;
    }
}
