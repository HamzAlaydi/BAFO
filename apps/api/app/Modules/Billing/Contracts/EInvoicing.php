<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Data\EInvoiceResult;
use App\Modules\Billing\Models\Invoice;

/**
 * The ZATCA e-invoicing provider (ARCHITECTURE §13.7). Driver `fake` today.
 */
interface EInvoicing
{
    /**
     * Clears (or reports) the invoice with the provider. Never throws for a provider-side
     * rejection: that is a `rejected` result with an error.
     */
    public function issue(Invoice $invoice): EInvoiceResult;
}
