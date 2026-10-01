<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Organizations\Pages;

use App\Modules\Admin\Filament\Resources\Organizations\OrganizationResource;
use Filament\Resources\Pages\ListRecords;

final class ListOrganizations extends ListRecords
{
    protected static string $resource = OrganizationResource::class;
}
