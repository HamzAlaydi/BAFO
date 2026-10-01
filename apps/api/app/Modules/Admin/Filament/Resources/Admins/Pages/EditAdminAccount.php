<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Admins\Pages;

use App\Modules\Admin\Actions\UpdateAdmin;
use App\Modules\Admin\Filament\Resources\Admins\AdminAccountResource;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Support\AdminActor;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditAdminAccount extends EditRecord
{
    protected static string $resource = AdminAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdminAccountResource::resetMfaAction(),
            AdminAccountResource::deleteAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Admin $record */
        /** @var array{name?: string, email?: string, password?: string|null, role?: string, is_active?: bool} $data */
        return ModuleAction::run(static fn (): Model => app(UpdateAdmin::class)->handle($record, $data, AdminActor::current()));
    }
}
