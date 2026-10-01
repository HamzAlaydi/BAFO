<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\CloseReasons\Pages;

use App\Modules\Admin\Filament\Resources\CloseReasons\CloseReasonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCloseReasons extends ListRecords
{
    protected static string $resource = CloseReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
