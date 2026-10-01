<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Payments\Pages;

use App\Modules\Admin\Filament\Resources\Payments\PaymentResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return array_map(
            fn (Action $action): Action => $action->after(fn () => $this->getRecord()->refresh()),
            PaymentResource::paymentActions(),
        );
    }
}
