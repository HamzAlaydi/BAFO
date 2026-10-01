<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Presets\Pages;

use App\Modules\Admin\Filament\Resources\Presets\CompetitionPresetResource;
use App\Modules\Admin\Filament\Support\EditsReferenceRecords;
use Filament\Resources\Pages\EditRecord;

final class EditCompetitionPreset extends EditRecord
{
    use EditsReferenceRecords;

    protected static string $resource = CompetitionPresetResource::class;
}
