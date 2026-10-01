<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Invoices\Pages;

use App\Modules\Admin\Filament\Resources\Invoices\InvoiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return array_map(
            fn (Action $action): Action => $action->after(fn () => $this->getRecord()->refresh()),
            InvoiceResource::invoiceActions(),
        );
    }
}
