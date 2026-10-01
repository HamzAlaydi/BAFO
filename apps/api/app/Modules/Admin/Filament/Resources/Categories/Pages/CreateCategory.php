<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Categories\Pages;

use App\Modules\Admin\Filament\Resources\Categories\CategoryResource;
use App\Modules\Admin\Filament\Support\CreatesReferenceRecords;
use Filament\Resources\Pages\CreateRecord;

final class CreateCategory extends CreateRecord
{
    use CreatesReferenceRecords;

    protected static string $resource = CategoryResource::class;
}
