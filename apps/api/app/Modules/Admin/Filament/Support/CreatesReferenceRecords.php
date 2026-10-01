<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Support;

use App\Modules\Admin\Actions\SaveReferenceRecord;
use App\Modules\Admin\Support\AdminActor;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * For the create page of a reference resource (plans, coupons, lookups): saves through the
 * audited SaveReferenceRecord Action instead of Filament's plain Eloquent create.
 *
 * @mixin CreateRecord
 */
trait CreatesReferenceRecords
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $model = $this->getModel();

        return ModuleAction::run(static fn (): Model => app(SaveReferenceRecord::class)->handle(new $model, $data, AdminActor::current()));
    }
}
