<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Presets\Pages;

use App\Modules\Admin\Filament\Resources\Presets\CompetitionPresetResource;
use App\Modules\Admin\Filament\Support\CreatesReferenceRecords;
use Filament\Resources\Pages\CreateRecord;

final class CreateCompetitionPreset extends CreateRecord
{
    use CreatesReferenceRecords;

    protected static string $resource = CompetitionPresetResource::class;
}
