<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Pages;

use App\Modules\Admin\Filament\Widgets\LiveCompetitionsTable;
use App\Modules\Admin\Filament\Widgets\PlatformStatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * §16 Dashboard: the platform counters (four in release scope `core`, seven in `full`,
 * PlatformStatsOverview) and the table of live competitions.
 */
final class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            PlatformStatsOverview::class,
            LiveCompetitionsTable::class,
        ];
    }

    public function getColumns(): int
    {
        return 1;
    }
}
