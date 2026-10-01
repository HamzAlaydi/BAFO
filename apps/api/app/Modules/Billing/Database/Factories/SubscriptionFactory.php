<?php

declare(strict_types=1);

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Models\Organization;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A current paid monthly subscription (started yesterday) with the payment snapshot of a
 * SAR 1,500 plan. The payment row itself is not created (`payment_id` null).
 *
 * @extends Factory<Subscription>
 */
final class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::now()->subDay();
        $unit = 150_000;
        $vat = Money::vat($unit);

        return [
            'organization_id' => Organization::factory(),
            'plan_id' => Plan::factory(),
            'source' => SubscriptionSource::Paid,
            'interval' => BillingInterval::Monthly,
            'seats' => static fn (array $attributes): int => Plan::query()->findOrFail($attributes['plan_id'])->seats ?? 4,
            'status' => SubscriptionStatus::Active,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMonthNoOverflow(),
            'unit_price_minor' => $unit,
            'subtotal_minor' => $unit,
            'discount_minor' => 0,
            'credit_minor' => 0,
            'vat_minor' => $vat,
            'total_minor' => $unit + $vat,
            'payment_id' => null,
            'activated_at' => $startsAt,
            'reminders_sent' => [],
        ];
    }

    /**
     * The 30-day trial (§13.3): no amounts.
     */
    public function trial(): self
    {
        $startsAt = CarbonImmutable::now()->subDay();

        return $this->withoutAmounts()->state([
            'source' => SubscriptionSource::Trial,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addDays(30),
            'activated_at' => $startsAt,
        ]);
    }

    /**
     * An admin grant (§13.3): no amounts. `granted_by_admin_id` stays null (domain modules do not
     * depend on the Admin module); pass one when the test needs it.
     */
    public function grant(): self
    {
        return $this->withoutAmounts()->state([
            'source' => SubscriptionSource::Grant,
            'grant_reason' => 'منحة تجريبية للشركاء',
        ]);
    }

    public function pendingPayment(): self
    {
        return $this->state([
            'status' => SubscriptionStatus::PendingPayment,
            'starts_at' => null,
            'ends_at' => null,
            'activated_at' => null,
        ]);
    }

    public function expired(): self
    {
        $startsAt = CarbonImmutable::now()->subMonths(2);

        return $this->state([
            'status' => SubscriptionStatus::Expired,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMonthNoOverflow(),
            'activated_at' => $startsAt,
            'expired_at' => $startsAt->addMonthNoOverflow(),
        ]);
    }

    private function withoutAmounts(): self
    {
        return $this->state([
            'interval' => null,
            'unit_price_minor' => null,
            'subtotal_minor' => null,
            'discount_minor' => null,
            'credit_minor' => null,
            'vat_minor' => null,
            'total_minor' => null,
        ]);
    }
}
