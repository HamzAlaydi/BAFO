<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Console\Commands;

use App\Modules\Bidding\Enums\BafoRoundStatus;
use App\Modules\Bidding\Jobs\EndBafoRound;
use App\Modules\Bidding\Models\BafoRound;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

/**
 * `bidding:tick` (ARCHITECTURE §12, every 10 s): the safety net that ends due BAFO rounds when
 * their delayed EndBafoRound job was lost.
 */
final class BiddingTickCommand extends Command
{
    protected $signature = 'bidding:tick';

    protected $description = 'Dispatch EndBafoRound for every running BAFO round past its cutoff';

    public function handle(): int
    {
        $count = 0;

        BafoRound::query()
            ->where('status', BafoRoundStatus::Running->value)
            ->where('cutoff_at', '<=', Date::now())
            ->orderBy('id')
            ->pluck('competition_id')
            ->each(static function (mixed $competitionId) use (&$count): void {
                EndBafoRound::dispatch((int) $competitionId);
                $count++;
            });

        $this->components->info("Due BAFO rounds: {$count}.");

        return self::SUCCESS;
    }
}
