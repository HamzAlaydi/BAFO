<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ApiClients\Pages;

use App\Modules\Admin\Filament\Resources\ApiClients\ApiClientResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewApiClient extends ViewRecord
{
    protected static string $resource = ApiClientResource::class;

    protected function getHeaderActions(): array
    {
        return array_map(
            fn (Action $action): Action => $action->after(fn () => $this->getRecord()->refresh()),
            ApiClientResource::clientActions(),
        );
    }
}
