<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Regions\Pages;

use App\Modules\Admin\Filament\Resources\Regions\RegionResource;
use App\Modules\Admin\Filament\Support\EditsReferenceRecords;
use Filament\Resources\Pages\EditRecord;

final class EditRegion extends EditRecord
{
    use EditsReferenceRecords;

    protected static string $resource = RegionResource::class;
}
