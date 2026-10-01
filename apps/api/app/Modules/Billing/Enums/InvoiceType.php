<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * `invoices.type` (ARCHITECTURE §5.7, §13.7).
 */
enum InvoiceType: string
{
    case TaxInvoice = 'tax_invoice';
    case CreditNote = 'credit_note';

    /** Label in the given locale (default: the app locale). Key: `billing.enums.invoice_type.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.invoice_type.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
