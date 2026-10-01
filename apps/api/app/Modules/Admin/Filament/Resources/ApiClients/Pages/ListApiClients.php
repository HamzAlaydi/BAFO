<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ApiClients\Pages;

use App\Modules\Admin\Filament\Resources\ApiClients\ApiClientResource;
use Filament\Resources\Pages\ListRecords;

final class ListApiClients extends ListRecords
{
    protected static string $resource = ApiClientResource::class;
}
