<?php

declare(strict_types=1);

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\CouponRedemption;
use App\Modules\Billing\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The redemption of a 10% coupon on a succeeded payment (SAR 150 off SAR 1,500).
 *
 * @extends Factory<CouponRedemption>
 */
final class CouponRedemptionFactory extends Factory
{
    protected $model = CouponRedemption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coupon_id' => Coupon::factory(),
            'payment_id' => Payment::factory()->succeeded(),
            'organization_id' => static fn (array $attributes): int => Payment::query()
                ->findOrFail($attributes['payment_id'])
                ->organization_id,
            'amount_minor' => 15_000,
            'redeemed_at' => now(),
        ];
    }
}
