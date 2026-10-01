<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Plans\Pages;

use App\Modules\Admin\Filament\Resources\Plans\PlanResource;
use App\Modules\Admin\Filament\Support\EditsReferenceRecords;
use Filament\Resources\Pages\EditRecord;

final class EditPlan extends EditRecord
{
    use EditsReferenceRecords;

    protected static string $resource = PlanResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PlanResource::normalize($data);
    }
}
