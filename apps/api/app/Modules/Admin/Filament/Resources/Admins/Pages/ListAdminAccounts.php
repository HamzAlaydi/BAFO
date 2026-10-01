<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Admins\Pages;

use App\Modules\Admin\Filament\Resources\Admins\AdminAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListAdminAccounts extends ListRecords
{
    protected static string $resource = AdminAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
