<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Regions\Pages;

use App\Modules\Admin\Filament\Resources\Regions\RegionResource;
use App\Modules\Admin\Filament\Support\CreatesReferenceRecords;
use Filament\Resources\Pages\CreateRecord;

final class CreateRegion extends CreateRecord
{
    use CreatesReferenceRecords;

    protected static string $resource = RegionResource::class;
}
