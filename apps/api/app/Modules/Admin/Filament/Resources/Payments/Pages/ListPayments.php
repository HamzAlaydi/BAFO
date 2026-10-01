<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Payments\Pages;

use App\Modules\Admin\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;

final class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}
