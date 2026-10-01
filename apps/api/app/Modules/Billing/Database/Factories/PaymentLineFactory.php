<?php

declare(strict_types=1);

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Enums\PaymentLineKind;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The plan line of a monthly `pro`-priced checkout.
 *
 * @extends Factory<PaymentLine>
 */
final class PaymentLineFactory extends Factory
{
    protected $model = PaymentLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'kind' => PaymentLineKind::Plan,
            'description' => ['ar' => 'باقة برو — اشتراك شهري', 'en' => 'Pro plan — monthly'],
            'quantity' => 1,
            'unit_price_minor' => 150_000,
            'net_minor' => 150_000,
            'ref_type' => null,
            'ref_id' => null,
        ];
    }

    public function sponsoredPasses(int $quantity = 2, int $unitPriceMinor = 20_000): self
    {
        return $this->state([
            'kind' => PaymentLineKind::SponsoredPass,
            'description' => ['ar' => 'تصريح مشاركة مغطّاة', 'en' => 'Sponsored participation pass'],
            'quantity' => $quantity,
            'unit_price_minor' => $unitPriceMinor,
            'net_minor' => $quantity * $unitPriceMinor,
        ]);
    }
}
