<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * `invoices.einvoice_status` (ARCHITECTURE §5.7, §13.7).
 */
enum EInvoiceStatus: string
{
    case Pending = 'pending';
    case Cleared = 'cleared';
    case Reported = 'reported';
    case Rejected = 'rejected';
    case Failed = 'failed';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.e_invoice_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.e_invoice_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
