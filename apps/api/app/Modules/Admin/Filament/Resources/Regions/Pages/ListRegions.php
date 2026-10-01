<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Regions\Pages;

use App\Modules\Admin\Filament\Resources\Regions\RegionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListRegions extends ListRecords
{
    protected static string $resource = RegionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
