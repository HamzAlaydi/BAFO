<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Categories\Pages;

use App\Modules\Admin\Filament\Resources\Categories\CategoryResource;
use App\Modules\Admin\Filament\Support\EditsReferenceRecords;
use Filament\Resources\Pages\EditRecord;

final class EditCategory extends EditRecord
{
    use EditsReferenceRecords;

    protected static string $resource = CategoryResource::class;
}
