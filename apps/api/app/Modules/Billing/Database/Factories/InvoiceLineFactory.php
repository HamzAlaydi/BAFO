<?php

declare(strict_types=1);

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The plan line of a SAR 1,500 invoice.
 *
 * @extends Factory<InvoiceLine>
 */
final class InvoiceLineFactory extends Factory
{
    protected $model = InvoiceLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => ['ar' => 'باقة برو — اشتراك شهري', 'en' => 'Pro plan — monthly'],
            'quantity' => 1,
            'unit_price_minor' => 150_000,
            'net_minor' => 150_000,
        ];
    }
}
