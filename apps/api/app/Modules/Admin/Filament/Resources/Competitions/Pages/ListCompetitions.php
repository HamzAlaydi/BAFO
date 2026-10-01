<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Competitions\Pages;

use App\Modules\Admin\Filament\Resources\Competitions\CompetitionResource;
use Filament\Resources\Pages\ListRecords;

final class ListCompetitions extends ListRecords
{
    protected static string $resource = CompetitionResource::class;
}
