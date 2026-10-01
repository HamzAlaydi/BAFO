<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\CloseReasons\Pages;

use App\Modules\Admin\Filament\Resources\CloseReasons\CloseReasonResource;
use App\Modules\Admin\Filament\Support\CreatesReferenceRecords;
use Filament\Resources\Pages\CreateRecord;

final class CreateCloseReason extends CreateRecord
{
    use CreatesReferenceRecords;

    protected static string $resource = CloseReasonResource::class;
}
