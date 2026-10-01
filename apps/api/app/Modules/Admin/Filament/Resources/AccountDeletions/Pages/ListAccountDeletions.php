<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\AccountDeletions\Pages;

use App\Modules\Admin\Filament\Resources\AccountDeletions\AccountDeletionResource;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Identity\Enums\DeletionStatus;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * The deletion queue, by status: pending first.
 */
final class ListAccountDeletions extends ListRecords
{
    protected static string $resource = AccountDeletionResource::class;

    public function getTabs(): array
    {
        $tabs = [];

        foreach ([DeletionStatus::Pending, DeletionStatus::Completed, DeletionStatus::Cancelled] as $status) {
            $tabs[$status->value] = Tab::make($status->label())
                ->modifyQueryUsing(static fn (Builder $query): Builder => $query->where('status', $status->value));
        }

        $tabs['all'] = Tab::make(Lang::get('common.all'));

        return $tabs;
    }
}
