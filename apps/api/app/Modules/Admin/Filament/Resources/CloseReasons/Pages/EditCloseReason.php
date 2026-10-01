<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\CloseReasons\Pages;

use App\Modules\Admin\Filament\Resources\CloseReasons\CloseReasonResource;
use App\Modules\Admin\Filament\Support\EditsReferenceRecords;
use Filament\Resources\Pages\EditRecord;

final class EditCloseReason extends EditRecord
{
    use EditsReferenceRecords;

    protected static string $resource = CloseReasonResource::class;
}
