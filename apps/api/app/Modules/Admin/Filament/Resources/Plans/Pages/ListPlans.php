<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Plans\Pages;

use App\Modules\Admin\Filament\Resources\Plans\PlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPlans extends ListRecords
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
