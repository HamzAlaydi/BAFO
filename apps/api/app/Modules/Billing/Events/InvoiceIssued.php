<?php

declare(strict_types=1);

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A tax invoice was cleared and its PDF is ready (ARCHITECTURE §10, §13.7). Listener: Notifications `invoice.issued`.
 */
final readonly class InvoiceIssued
{
    use Dispatchable, SerializesModels;

    public function __construct(public Invoice $invoice) {}
}
