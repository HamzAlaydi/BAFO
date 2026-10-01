<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\LegalDocuments\Pages;

use App\Modules\Admin\Filament\Resources\LegalDocuments\LegalDocumentResource;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Platform\Actions\SaveLegalDocumentDraft;
use App\Modules\Platform\Models\LegalDocument;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Edits a draft version → Platform `SaveLegalDocumentDraft`; publishes it → `PublishLegalDocument`.
 */
final class EditLegalDocument extends EditRecord
{
    protected static string $resource = LegalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LegalDocumentResource::publishAction()
                ->successRedirectUrl(fn (): string => LegalDocumentResource::getUrl('view', ['record' => $this->getRecord()])),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var LegalDocument $record */
        return ModuleAction::run(static fn (): Model => app(SaveLegalDocumentDraft::class)->handle($record, $data, AdminActor::current()));
    }
}
