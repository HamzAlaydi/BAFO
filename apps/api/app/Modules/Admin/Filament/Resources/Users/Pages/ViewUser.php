<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Pages;

use App\Modules\Admin\Filament\Resources\Users\MembershipStatusActions;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

final class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return array_map(
            fn ($action) => $action->record($this->getRecord())->after(fn () => $this->getRecord()->refresh()),
            MembershipStatusActions::make(static fn (Model $record): ?Membership => $record instanceof User ? $record->membership()->first() : null),
        );
    }
}
