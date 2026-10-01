<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Plans\Pages;

use App\Modules\Admin\Filament\Resources\Plans\PlanResource;
use App\Modules\Admin\Filament\Support\CreatesReferenceRecords;
use Filament\Resources\Pages\CreateRecord;

final class CreatePlan extends CreateRecord
{
    use CreatesReferenceRecords;

    protected static string $resource = PlanResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PlanResource::normalize($data);
    }
}
