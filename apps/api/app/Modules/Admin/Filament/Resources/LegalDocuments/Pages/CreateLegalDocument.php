<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\LegalDocuments\Pages;

use App\Modules\Admin\Filament\Resources\LegalDocuments\LegalDocumentResource;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Platform\Actions\SaveLegalDocumentDraft;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * A new draft version → Platform `SaveLegalDocumentDraft`.
 */
final class CreateLegalDocument extends CreateRecord
{
    protected static string $resource = LegalDocumentResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return ModuleAction::run(static fn (): Model => app(SaveLegalDocumentDraft::class)->handle(null, $data, AdminActor::current()));
    }
}
