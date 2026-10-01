<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Admins\Pages;

use App\Modules\Admin\Actions\CreateAdmin;
use App\Modules\Admin\Filament\Resources\Admins\AdminAccountResource;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Support\AdminActor;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateAdminAccount extends CreateRecord
{
    protected static string $resource = AdminAccountResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var array{name: string, email: string, password: string, role: string, is_active?: bool} $data */
        return ModuleAction::run(static fn (): Model => app(CreateAdmin::class)->handle($data, AdminActor::current()));
    }
}
