<?php

declare(strict_types=1);

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Enums\CouponKind;
use App\Modules\Billing\Enums\CouponScope;
use App\Modules\Billing\Enums\DiscountType;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Identity\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An active 10% coupon for any purchase, valid for 30 days, 100 redemptions, once per
 * organization. `voucher()` makes an organization-scoped voucher (§13.4).
 *
 * @extends Factory<Coupon>
 */
final class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'BAFO'.$this->faker->unique()->numerify('####'),
            'kind' => CouponKind::Coupon,
            'discount_type' => DiscountType::Percent,
            'percent_bps' => 1000,
            'amount_minor' => null,
            'balance_minor' => null,
            'applies_to' => CouponScope::Any,
            'organization_id' => null,
            'max_redemptions' => 100,
            'redemptions_count' => 0,
            'per_organization_limit' => 1,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(30),
            'is_active' => true,
            'reason' => null,
        ];
    }

    public function fixed(int $amountMinor = 50_000): self
    {
        return $this->state([
            'discount_type' => DiscountType::Fixed,
            'percent_bps' => null,
            'amount_minor' => $amountMinor,
        ]);
    }

    public function appliesTo(CouponScope $scope): self
    {
        return $this->state(['applies_to' => $scope]);
    }

    /**
     * An organization voucher (`V-` + 10 `[A-Z0-9]`), valid 12 months, governed by its balance.
     */
    public function voucher(?Organization $organization = null, int $valueMinor = 60_000): self
    {
        return $this->state(fn (): array => [
            'code' => 'V-'.Str::upper(Str::random(10)),
            'kind' => CouponKind::Voucher,
            'discount_type' => DiscountType::Fixed,
            'percent_bps' => null,
            'amount_minor' => $valueMinor,
            'balance_minor' => $valueMinor,
            'organization_id' => $organization !== null ? $organization->id : Organization::factory(),
            'max_redemptions' => null,
            'per_organization_limit' => null,
            'valid_from' => now(),
            'valid_until' => now()->addMonths(12),
            'reason' => 'رصيد تصاريح غير مستخدمة',
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn (): array => ['valid_from' => now()->subDays(60), 'valid_until' => now()->subDay()]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
