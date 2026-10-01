<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Coupons\Pages;

use App\Modules\Admin\Filament\Resources\Coupons\CouponResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCoupons extends ListRecords
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
