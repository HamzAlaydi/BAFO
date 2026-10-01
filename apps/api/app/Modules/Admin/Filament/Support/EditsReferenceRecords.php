<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Support;

use App\Modules\Admin\Actions\DeleteReferenceRecord;
use App\Modules\Admin\Actions\SaveReferenceRecord;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Admin\Support\ReferenceRecords;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * For the edit page of a reference resource (plans, coupons, lookups): saves and deletes
 * through the audited Admin Actions. A record other rows still reference cannot be deleted
 * (deactivate it instead).
 *
 * @mixin EditRecord
 */
trait EditsReferenceRecords
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return ModuleAction::run(static fn (): Model => app(SaveReferenceRecord::class)->handle($record, $data, AdminActor::current()));
    }

    protected function getHeaderActions(): array
    {
        return [self::referenceDeleteAction()];
    }

    public static function referenceDeleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->visible(static fn (Model $record): bool => ! ReferenceRecords::isInUse($record))
            ->using(static function (Model $record): bool {
                ModuleAction::run(static fn () => app(DeleteReferenceRecord::class)->handle($record, AdminActor::current()));

                return true;
            });
    }
}
