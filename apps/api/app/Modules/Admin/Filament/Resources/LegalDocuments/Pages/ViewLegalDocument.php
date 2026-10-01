<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\LegalDocuments\Pages;

use App\Modules\Admin\Filament\Resources\LegalDocuments\LegalDocumentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewLegalDocument extends ViewRecord
{
    protected static string $resource = LegalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            LegalDocumentResource::publishAction()->after(fn () => $this->getRecord()->refresh()),
        ];
    }
}
