<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ContactMessages\Pages;

use App\Modules\Admin\Filament\Resources\ContactMessages\ContactMessageResource;
use Filament\Resources\Pages\ListRecords;

final class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;
}
