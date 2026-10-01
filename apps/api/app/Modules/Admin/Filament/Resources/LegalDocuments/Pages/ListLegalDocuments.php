<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\LegalDocuments\Pages;

use App\Modules\Admin\Filament\Resources\LegalDocuments\LegalDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListLegalDocuments extends ListRecords
{
    protected static string $resource = LegalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
