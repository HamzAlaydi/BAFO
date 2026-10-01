<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Presets\Pages;

use App\Modules\Admin\Filament\Resources\Presets\CompetitionPresetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCompetitionPresets extends ListRecords
{
    protected static string $resource = CompetitionPresetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
