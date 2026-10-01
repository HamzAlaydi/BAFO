<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Vouchers\Pages;

use App\Modules\Admin\Filament\Resources\Vouchers\VoucherResource;
use Filament\Resources\Pages\ListRecords;

final class ListVouchers extends ListRecords
{
    protected static string $resource = VoucherResource::class;
}
